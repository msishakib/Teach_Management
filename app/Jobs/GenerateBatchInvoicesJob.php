<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\User;
use App\Services\FeeInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBatchInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $batchId,
        public string $month,
        public ?int $actorId = null,
        public int $afterEnrollmentId = 0,
        public int $chunkSize = 100,
    ) {}

    public function handle(FeeInvoiceService $feeInvoices): void
    {
        $batch = Batch::query()->findOrFail($this->batchId);
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        $result = $feeInvoices->generateChunk(
            batch: $batch,
            month: $this->month,
            afterEnrollmentId: $this->afterEnrollmentId,
            limit: $this->chunkSize,
            actor: $actor,
        );

        if (($result['processed'] ?? 0) >= $this->chunkSize && ($result['last_enrollment_id'] ?? null)) {
            self::dispatch(
                $this->batchId,
                $this->month,
                $this->actorId,
                (int) $result['last_enrollment_id'],
                $this->chunkSize,
            );
        }
    }
}
