<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student.name' => ['sometimes', 'string', 'max:255'],
            'student.email' => ['sometimes', 'email', 'max:255'],
            'student.phone' => ['sometimes', 'string', 'max:32'],
            'student.document' => ['nullable', 'string', 'max:32'],
            'payment_status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
        ];
    }
}
