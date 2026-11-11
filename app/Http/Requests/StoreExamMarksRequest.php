<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamMarksRequest extends FormRequest
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
            'exam_subject_id' => ['required', 'integer', 'exists:exam_subjects,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array'],
            'rows.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'rows.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_absent' => ['sometimes', 'boolean'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
