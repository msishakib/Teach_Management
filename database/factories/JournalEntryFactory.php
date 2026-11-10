<?php

namespace Database\Factories;

use App\Enums\JournalEntryStatus;
use App\Enums\VoucherType;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'entry_no' => strtoupper(fake()->unique()->bothify('JV-####-####')),
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'reference_type' => null,
            'reference_id' => null,
            'narration' => fake()->optional()->sentence(),
            'status' => JournalEntryStatus::Draft,
            'created_by' => User::factory(),
            'posted_at' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => JournalEntryStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
