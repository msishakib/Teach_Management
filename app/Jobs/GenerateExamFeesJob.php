<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\User;
use App\Services\FeeInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateExamFeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $examId,
        public ?int $actorId = null,
        public int $afterEnrollmentId = 0,
        public int $chunkSize = 100,
    ) {}

    public function handle(FeeInvoiceService $feeInvoices): void
    {
        $exam = Exam::query()->findOrFail($this->examId);
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        $result = $feeInvoices->generateExamFeesChunk(
            exam: $exam,
            afterEnrollmentId: $this->afterEnrollmentId,
            limit: $this->chunkSize,
            actor: $actor,
        );

        if (($result['processed'] ?? 0) >= $this->chunkSize && ($result['last_enrollment_id'] ?? null)) {
            self::dispatch(
                $this->examId,
                $this->actorId,
                (int) $result['last_enrollment_id'],
                $this->chunkSize,
            );
        }
    }
}
