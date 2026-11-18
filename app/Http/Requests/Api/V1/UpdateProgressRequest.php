<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'completed' => ['sometimes', 'boolean'],
        ];
    }
}
