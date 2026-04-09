<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyEnrollmentsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $normalized = mb_strtolower(trim($data['email']));

        $student = Student::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        if ($student === null) {
            return EnrollmentResource::collection(collect());
        }

        $enrollments = $student->enrollments()
            ->with('course')
            ->orderByDesc('enrolled_at')
            ->get()
            ->filter(fn ($e) => $e->course !== null)
            ->values();

        return EnrollmentResource::collection($enrollments);
    }
}
