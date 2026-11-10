<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\StoreExamSubjectRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExamWorkflowService
{
    public function __construct(
        protected FeeInvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Exam
    {
        $validated = Validator::make($attributes, (new StoreExamRequest)->rules())->validate();
        $validated['status'] = ExamStatus::Draft;

        return Exam::query()->create($validated);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Exam $exam, array $attributes): Exam
    {
        unset($attributes['status']);

        $validated = Validator::make($attributes, (new UpdateExamRequest)->rules())->validate();
        $exam->update($validated);

        return $exam->refresh();
    }

    public function transition(Exam $exam, ExamStatus $to, ?User $actor = null): Exam
    {
        if (! $exam->status->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition exam from {$exam->status->getLabel()} to {$to->getLabel()}.",
            ]);
        }

        if ($to === ExamStatus::Scheduled && $exam->subjects()->count() === 0) {
            throw ValidationException::withMessages([
                'status' => 'Add at least one exam subject before scheduling.',
            ]);
        }

        $exam->update(['status' => $to]);

        return $exam->refresh();
    }

    public function schedule(Exam $exam, bool $generateFees = false, ?User $actor = null): Exam
    {
        $exam = $this->transition($exam, ExamStatus::Scheduled, $actor);

        if ($generateFees && $exam->hasFee()) {
            $this->invoices->generateExamFees($exam, $actor);
        }

        return $exam;
    }

    public function start(Exam $exam, ?User $actor = null): Exam
    {
        return $this->transition($exam, ExamStatus::Ongoing, $actor);
    }

    public function openMarksEntry(Exam $exam, ?User $actor = null): Exam
    {
        return $this->transition($exam, ExamStatus::MarksEntry, $actor);
    }

    public function publish(Exam $exam, ?User $actor = null): Exam
    {
        return $this->transition($exam, ExamStatus::Published, $actor);
    }

    public function close(Exam $exam, ?User $actor = null): Exam
    {
        return $this->transition($exam, ExamStatus::Closed, $actor);
    }

    public function revertToDraft(Exam $exam, ?User $actor = null): Exam
    {
        return $this->transition($exam, ExamStatus::Draft, $actor);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addSubject(Exam $exam, array $attributes): ExamSubject
    {
        $data = StoreExamSubjectRequest::validateFor($exam, $attributes);

        return $exam->subjects()->create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSubject(ExamSubject $examSubject, array $attributes): ExamSubject
    {
        $data = StoreExamSubjectRequest::validateFor(
            $examSubject->exam,
            $attributes,
            $examSubject->id,
        );

        $examSubject->update($data);

        return $examSubject->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validateSubjectAttributes(Exam $exam, array $attributes, ?int $ignoreSubjectId = null): array
    {
        return StoreExamSubjectRequest::validateFor($exam, $attributes, $ignoreSubjectId);
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function generateFees(Exam $exam, ?User $actor = null): array
    {
        if (! in_array($exam->status, [ExamStatus::Scheduled, ExamStatus::Ongoing], true)) {
            throw ValidationException::withMessages([
                'status' => 'Exam fees can only be generated when the exam is scheduled or ongoing.',
            ]);
        }

        if (! $exam->hasFee()) {
            throw ValidationException::withMessages([
                'fee_amount' => 'Set an exam fee amount before generating invoices.',
            ]);
        }

        return $this->invoices->generateExamFees($exam, $actor);
    }
}
