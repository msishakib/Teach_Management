<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:191'],
            'dob' => ['nullable','date'],
            'gender' => ['nullable','in:male,female,other'],
            'phone' => ['required','string','max:30'],
            'email' => ['nullable','email','max:191'],
            'address' => ['nullable','string'],
            'guardian_name' => ['nullable','string','max:191'],
            'guardian_phone' => ['nullable','string','max:30'],
            'course_id' => ['required','exists:courses,id'],
            'batch_id' => ['nullable','exists:batches,id'],
            'photo' => ['nullable','image','max:2048'],
            'documents.*' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'hp_name' => ['nullable','max:0'], // honeypot field must be empty
        ];
    }

    public function messages(): array
    {
        return [
            'hp_name.max' => 'Spam detected.',
        ];
    }
}

