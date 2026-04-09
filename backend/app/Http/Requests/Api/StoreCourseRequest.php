<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'active' => ['sometimes', 'boolean'],
            'enrollment_starts_at' => ['nullable', 'date'],
            'enrollment_ends_at' => ['nullable', 'date'],
            'max_seats' => ['nullable', 'integer', 'min:1'],
            'banner' => ['nullable', 'image', 'max:5120'],
            'material_files' => ['nullable', 'array', 'max:30'],
            'material_files.*' => ['file', 'max:20480'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $max = $this->input('max_seats');
        $merge = [
            'enrollment_starts_at' => $this->filled('enrollment_starts_at') ? $this->input('enrollment_starts_at') : null,
            'enrollment_ends_at' => $this->filled('enrollment_ends_at') ? $this->input('enrollment_ends_at') : null,
            'max_seats' => $max === '' || $max === null ? null : $max,
        ];
        if ($this->has('price_cents') && $this->input('price_cents') !== '' && $this->input('price_cents') !== null) {
            $merge['price_cents'] = (int) $this->input('price_cents');
        }
        if ($this->has('description')) {
            $merge['description'] = trim((string) $this->input('description'));
        }
        $this->merge($merge);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->filled('enrollment_starts_at') ? $this->input('enrollment_starts_at') : null;
            $end = $this->filled('enrollment_ends_at') ? $this->input('enrollment_ends_at') : null;
            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $validator->errors()->add(
                    'enrollment_ends_at',
                    'A data final deve ser igual ou posterior à inicial.'
                );
            }
        });
    }
}
