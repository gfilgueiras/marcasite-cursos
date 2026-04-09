<?php

namespace App\Http\Requests\Api;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'active' => ['sometimes', 'boolean'],
            'enrollment_starts_at' => ['sometimes', 'nullable', 'date'],
            'enrollment_ends_at' => ['sometimes', 'nullable', 'date'],
            'max_seats' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'banner' => ['nullable', 'image', 'max:5120'],
            'remove_banner' => ['sometimes', 'boolean'],
            'material_files' => ['nullable', 'array', 'max:30'],
            'material_files.*' => ['file', 'max:20480'],
            'existing_materials' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('price_cents') && $this->input('price_cents') !== '' && $this->input('price_cents') !== null) {
            $merge['price_cents'] = (int) $this->input('price_cents');
        }
        if ($this->exists('max_seats')) {
            $max = $this->input('max_seats');
            $merge['max_seats'] = $max === '' || $max === null ? null : $max;
        }
        if ($this->exists('enrollment_starts_at')) {
            $merge['enrollment_starts_at'] = $this->filled('enrollment_starts_at')
                ? $this->input('enrollment_starts_at')
                : null;
        }
        if ($this->exists('enrollment_ends_at')) {
            $merge['enrollment_ends_at'] = $this->filled('enrollment_ends_at')
                ? $this->input('enrollment_ends_at')
                : null;
        }
        if ($this->has('description')) {
            $merge['description'] = trim((string) $this->input('description'));
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Course $course */
            $course = $this->route('course');
            $start = $this->filled('enrollment_starts_at')
                ? $this->input('enrollment_starts_at')
                : $course->enrollment_starts_at?->format('Y-m-d');
            $end = $this->filled('enrollment_ends_at')
                ? $this->input('enrollment_ends_at')
                : $course->enrollment_ends_at?->format('Y-m-d');
            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $validator->errors()->add(
                    'enrollment_ends_at',
                    'A data final deve ser igual ou posterior à inicial.'
                );
            }
        });
    }
}
