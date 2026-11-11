<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{6,40}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'message' => ['nullable', 'string', 'max:2000'],
            'company_website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
