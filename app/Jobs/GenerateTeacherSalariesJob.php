<?php

namespace App\Jobs;

use App\Services\TeacherSalaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTeacherSalariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $month,
        public ?int $actorId = null,
        public int $afterTeacherId = 0,
        public int $chunkSize = 100,
    ) {}

    public function handle(TeacherSalaryService $salaries): void
    {
        $result = $salaries->generateChunk(
            month: $this->month,
            afterTeacherId: $this->afterTeacherId,
            limit: $this->chunkSize,
        );

        if (($result['processed'] ?? 0) >= $this->chunkSize && ($result['last_teacher_id'] ?? null)) {
            self::dispatch(
                $this->month,
                $this->actorId,
                (int) $result['last_teacher_id'],
                $this->chunkSize,
            );
        }
    }
}
