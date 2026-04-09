<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidBrazilianPhone;
use App\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('phone')) {
            $merge['phone'] = preg_replace('/\D+/', '', (string) $this->input('phone'));
        }

        if ($this->has('document')) {
            $merge['document'] = preg_replace('/\D+/', '', (string) $this->input('document'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('active', true)],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:32', new ValidBrazilianPhone],
            'document' => ['required', 'string', 'size:11', new ValidCpf],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'curso',
            'name' => 'nome',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'document' => 'CPF',
        ];
    }
}
