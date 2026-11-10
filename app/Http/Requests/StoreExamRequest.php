<?php

namespace App\Http\Requests;

use App\Enums\ExamStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
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
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'exam_type_id' => ['required', 'integer', 'exists:exam_types,id'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::enum(ExamStatus::class)],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'grading_scale_id' => ['nullable', 'integer', 'exists:grading_scales,id'],
        ];
    }
}
