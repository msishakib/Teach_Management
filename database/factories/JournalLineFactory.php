<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => 0,
            'credit' => 0,
            'student_id' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
