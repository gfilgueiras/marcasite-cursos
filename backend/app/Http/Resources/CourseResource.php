<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Course */
class CourseResource extends JsonResource
{
    /**
     * @return list<array{name: string, path: string, url: string|null}>
     */
    protected function materialsForAdmin(): array
    {
        return collect($this->materials ?? [])
            ->map(function (mixed $row) {
                if (! is_array($row)) {
                    return null;
                }
                $path = (string) ($row['path'] ?? '');

                return [
                    'name' => (string) ($row['name'] ?? ($path !== '' ? basename($path) : '')),
                    'path' => $path,
                    'url' => $path !== '' ? Storage::disk('public')->url($path) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function toArray(Request $request): array
    {
        $attrs = $this->resource->getAttributes();
        $enrollmentsCount = array_key_exists('enrollments_count', $attrs)
            ? (int) $attrs['enrollments_count']
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'banner_url' => $this->banner_path
                ? Storage::disk('public')->url($this->banner_path)
                : null,
            'materials' => $this->when(
                $request->is('api/v1/admin/*'),
                fn () => $this->materialsForAdmin()
            ),
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'active' => $this->active,
            'enrollment_starts_at' => $this->enrollment_starts_at?->format('Y-m-d'),
            'enrollment_ends_at' => $this->enrollment_ends_at?->format('Y-m-d'),
            'max_seats' => $this->max_seats,
            'enrollments_count' => $this->when($enrollmentsCount !== null, $enrollmentsCount),
            'remaining_seats' => $this->when(
                $enrollmentsCount !== null && $this->max_seats !== null,
                max(0, $this->max_seats - $enrollmentsCount)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
