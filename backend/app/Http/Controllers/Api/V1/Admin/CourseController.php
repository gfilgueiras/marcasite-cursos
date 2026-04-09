<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCourseRequest;
use App\Http\Requests\Api\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->withCount('enrollments')
            ->orderBy('name')
            ->get();

        return CourseResource::collection($courses);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['banner'], $data['material_files']);
        $data['currency'] = $data['currency'] ?? 'brl';
        $data['active'] = $data['active'] ?? true;

        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('course-banners', 'public');
        }

        $data['materials'] = $this->storeMaterialUploads($request);

        $course = Course::query()->create($data);
        $course->loadCount('enrollments');

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    public function show(Course $course): CourseResource
    {
        $course->loadCount('enrollments');

        return new CourseResource($course);
    }

    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $data = $request->validated();
        unset($data['banner'], $data['material_files'], $data['existing_materials']);

        if ($request->boolean('remove_banner')) {
            if ($course->banner_path) {
                Storage::disk('public')->delete($course->banner_path);
            }
            $data['banner_path'] = null;
        }

        if ($request->hasFile('banner')) {
            if ($course->banner_path) {
                Storage::disk('public')->delete($course->banner_path);
            }
            $data['banner_path'] = $request->file('banner')->store('course-banners', 'public');
        }

        unset($data['remove_banner']);

        if ($request->has('existing_materials') || $request->hasFile('material_files')) {
            $data['materials'] = $this->mergeCourseMaterials($request, $course);
        }

        $course->update($data);
        $course = $course->fresh();
        $course->loadCount('enrollments');

        return new CourseResource($course);
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    private function storeMaterialUploads(StoreCourseRequest $request): array
    {
        $out = [];
        foreach ($request->file('material_files', []) as $file) {
            $out[] = [
                'path' => $file->store('course-materials', 'public'),
                'name' => $file->getClientOriginalName(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    private function mergeCourseMaterials(Request $request, Course $course): array
    {
        $old = $course->materials ?? [];
        $oldPaths = collect($old)->pluck('path')->filter()->all();

        $raw = $request->input('existing_materials', '[]');
        $keep = is_string($raw) ? json_decode($raw, true) : [];
        if (! is_array($keep)) {
            $keep = [];
        }

        $kept = [];
        foreach ($keep as $item) {
            if (! is_array($item) || empty($item['path'])) {
                continue;
            }
            $path = (string) $item['path'];
            if (! in_array($path, $oldPaths, true)) {
                continue;
            }
            $kept[] = [
                'path' => $path,
                'name' => (string) ($item['name'] ?? basename($path)),
            ];
        }

        $keepPaths = collect($kept)->pluck('path')->all();
        foreach ($old as $row) {
            if (! is_array($row)) {
                continue;
            }
            $p = $row['path'] ?? null;
            if (! is_string($p) || $p === '') {
                continue;
            }
            if (! in_array($p, $keepPaths, true)) {
                Storage::disk('public')->delete($p);
            }
        }

        $new = [];
        foreach ($request->file('material_files', []) as $file) {
            $new[] = [
                'path' => $file->store('course-materials', 'public'),
                'name' => $file->getClientOriginalName(),
            ];
        }

        return array_values(array_merge($kept, $new));
    }

    public function destroy(Course $course): JsonResponse
    {
        if ($course->banner_path) {
            Storage::disk('public')->delete($course->banner_path);
        }
        foreach ($course->materials ?? [] as $row) {
            $p = is_array($row) ? ($row['path'] ?? null) : null;
            if (is_string($p) && $p !== '') {
                Storage::disk('public')->delete($p);
            }
        }

        $course->delete();

        return response()->json(null, 204);
    }
}
