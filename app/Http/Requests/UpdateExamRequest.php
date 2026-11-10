<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
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
            'academic_session_id' => ['sometimes', 'required', 'integer', 'exists:academic_sessions,id'],
            'exam_type_id' => ['sometimes', 'required', 'integer', 'exists:exam_types,id'],
            'batch_id' => ['sometimes', 'required', 'integer', 'exists:batches,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'grading_scale_id' => ['nullable', 'integer', 'exists:grading_scales,id'],
        ];
    }
}
