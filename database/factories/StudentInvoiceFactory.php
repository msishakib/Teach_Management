<?php

namespace Database\Factories;

use App\Enums\StudentInvoiceStatus;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentInvoice>
 */
class StudentInvoiceFactory extends Factory
{
    protected $model = StudentInvoice::class;

    public function definition(): array
    {
        $total = number_format((float) fake()->randomFloat(2, 500, 3000), 2, '.', '');

        return [
            'student_id' => Student::factory(),
            'batch_id' => Batch::factory(),
            'invoice_no' => strtoupper(fake()->unique()->bothify('INV-####-####')),
            'month' => now()->format('Y-m'),
            'due_date' => now()->addDays(10)->toDateString(),
            'total' => $total,
            'discount' => '0.00',
            'paid' => '0.00',
            'due' => $total,
            'status' => StudentInvoiceStatus::Unpaid,
            'journal_entry_id' => null,
        ];
    }
}
