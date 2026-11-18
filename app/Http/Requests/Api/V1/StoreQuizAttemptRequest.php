<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quiz_id' => ['required_without:attempt_id', 'integer', 'exists:quizzes,id'],
            'attempt_id' => ['nullable', 'integer', 'exists:quiz_attempts,id'],
            'submit' => ['sometimes', 'boolean'],
            'answers' => ['sometimes', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:questions,id'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'answers.*.selected_option_ids.*' => ['integer'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
