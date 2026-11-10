<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamSubjectRequest extends FormRequest
{
    public ?Exam $exam = null;

    public ?int $exam_subject_id = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::rulesFor($this->exam(), $this->examSubjectId());
    }

    public function withValidator(Validator $validator): void
    {
        self::applyAfterValidation($validator, $this->exam());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function validateFor(Exam $exam, array $attributes, ?int $ignoreSubjectId = null): array
    {
        $validator = ValidatorFacade::make($attributes, self::rulesFor($exam, $ignoreSubjectId));
        self::applyAfterValidation($validator, $exam);

        return $validator->validate();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Exam $exam, ?int $ignoreSubjectId = null): array
    {
        return [
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id',
                Rule::unique('exam_subjects', 'subject_id')
                    ->where(fn ($query) => $query->where('exam_id', $exam?->id ?? 0))
                    ->ignore($ignoreSubjectId),
            ],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'full_marks' => ['required', 'numeric', 'min:0'],
            'pass_marks' => ['required', 'numeric', 'min:0'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ];
    }

    public static function applyAfterValidation(Validator $validator, ?Exam $exam): void
    {
        $validator->after(function (Validator $validator) use ($exam): void {
            $data = $validator->getData();
            $fullMarks = $data['full_marks'] ?? null;
            $passMarks = $data['pass_marks'] ?? null;

            if ($fullMarks !== null && $passMarks !== null
                && bccomp((string) $passMarks, (string) $fullMarks, 2) > 0) {
                $validator->errors()->add('pass_marks', 'Pass marks must be less than or equal to full marks.');
            }

            if (! $exam || empty($data['exam_date'])) {
                return;
            }

            $examDate = (string) $data['exam_date'];

            if ($exam->start_date && $examDate < $exam->start_date->toDateString()) {
                $validator->errors()->add('exam_date', 'Exam subject date must fall on or after the exam start date.');
            }

            if ($exam->end_date && $examDate > $exam->end_date->toDateString()) {
                $validator->errors()->add('exam_date', 'Exam subject date must fall on or before the exam end date.');
            }
        });
    }

    protected function exam(): ?Exam
    {
        return $this->exam ?? $this->route('exam');
    }

    protected function examSubjectId(): ?int
    {
        return $this->exam_subject_id
            ?? $this->route('exam_subject')?->id;
    }
}
