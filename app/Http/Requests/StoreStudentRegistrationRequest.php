<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],

            'national_id' => ['nullable', 'string', 'max:50'],
            'birth_certificate_no' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],

            'present_address' => ['required', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],

            'last_institution' => ['nullable', 'string', 'max:191'],
            'last_exam' => ['nullable', 'string', 'max:50'],
            'last_result' => ['nullable', 'string', 'max:50'],
            'passing_year' => ['nullable', 'integer', 'between:1950,'.now()->year],

            'hp_name' => ['nullable', 'max:0'], // honeypot field must be empty
        ];
    }

    public function messages(): array
    {
        return [
            'hp_name.max' => 'Spam detected.',
        ];
    }
}
