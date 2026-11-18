<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\JournalEntryStatus;
use App\Enums\StudentInvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\VoucherType;
use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        DB::disableQueryLog();
        ini_set('memory_limit', '1024M');

        $now = now();
        $createdBy = User::query()->value('id') ?? User::factory()->create()->id;

        // Ensure base data exists.
        $course = Course::query()->first() ?? Course::query()->create([
            'code' => 'PERF',
            'name' => 'Performance Course',
            'is_active' => true,
        ]);
        $session = AcademicSession::query()->first() ?? AcademicSession::query()->create([
            'name' => 'Perf Session',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);
        $batch = Batch::query()->first() ?? Batch::query()->create([
            'name' => 'Perf Batch',
            'course_id' => $course->id,
            'session_id' => $session->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'capacity' => 10000,
            'status' => 'active',
        ]);

        $debitAccount = Account::query()->where('is_group', false)->where('type', AccountType::Asset)->value('id')
            ?? Account::query()->create([
                'code' => '1199',
                'name' => 'Perf Debit',
                'type' => AccountType::Asset,
                'is_group' => false,
                'is_cash_or_bank' => false,
                'opening_balance' => 0,
                'status' => AccountStatus::Active,
            ])->id;
        $creditAccount = Account::query()->where('is_group', false)->where('type', AccountType::Income)->value('id')
            ?? Account::query()->create([
                'code' => '4999',
                'name' => 'Perf Credit',
                'type' => AccountType::Income,
                'is_group' => false,
                'is_cash_or_bank' => false,
                'opening_balance' => 0,
                'status' => AccountStatus::Active,
            ])->id;

        $this->command?->info('Seeding 5,000 students...');
        $studentRows = [];
        for ($i = 1; $i <= 5000; $i++) {
            $studentRows[] = [
                'admission_no' => 'PERF-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'name' => 'Perf Student '.$i,
                'dob' => null,
                'gender' => Gender::Other->value,
                'email' => null,
                'phone' => null,
                'address' => null,
                'photo' => null,
                'status' => StudentStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
                'user_id' => null,
            ];
            if (count($studentRows) === 1000) {
                DB::table('students')->insertOrIgnore($studentRows);
                $studentRows = [];
            }
        }
        if ($studentRows) {
            DB::table('students')->insertOrIgnore($studentRows);
        }

        $studentIds = DB::table('students')->where('admission_no', 'like', 'PERF-%')->orderBy('id')->pluck('id')->all();
        if (count($studentIds) < 5000) {
            // fallback to any students
            $studentIds = DB::table('students')->orderBy('id')->limit(5000)->pluck('id')->all();
        }

        $this->command?->info('Seeding enrollments...');
        $enrollRows = [];
        foreach (array_slice($studentIds, 0, 5000) as $sid) {
            $enrollRows[] = [
                'student_id' => $sid,
                'batch_id' => $batch->id,
                'enrolled_on' => $now->toDateString(),
                'status' => EnrollmentStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
                'enrolled_via' => 'offline',
                'expires_at' => null,
            ];
            if (count($enrollRows) === 1000) {
                DB::table('enrollments')->insertOrIgnore($enrollRows);
                $enrollRows = [];
            }
        }
        if ($enrollRows) {
            DB::table('enrollments')->insertOrIgnore($enrollRows);
        }

        $this->command?->info('Seeding 100,000 invoices...');
        $invoiceRows = [];
        $invoiceCount = 100000;
        $studentCount = max(1, count($studentIds));
        for ($i = 1; $i <= $invoiceCount; $i++) {
            $sid = $studentIds[($i - 1) % $studentCount];
            $total = 1000.00;
            $due = ($i % 3 === 0) ? 0.00 : $total;
            $paid = $total - $due;
            $status = $due <= 0 ? StudentInvoiceStatus::Paid->value : StudentInvoiceStatus::Unpaid->value;
            $invoiceRows[] = [
                'student_id' => $sid,
                'batch_id' => $batch->id,
                'exam_id' => null,
                'invoice_no' => 'PINV-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'month' => sprintf('2026-%02d', (($i - 1) % 12) + 1),
                'due_date' => $now->copy()->subDays($i % 365)->toDateString(),
                'total' => $total,
                'discount' => 0,
                'paid' => $paid,
                'due' => $due,
                'status' => $status,
                'journal_entry_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($invoiceRows) === 2000) {
                DB::table('student_invoices')->insertOrIgnore($invoiceRows);
                $invoiceRows = [];
            }
        }
        if ($invoiceRows) {
            DB::table('student_invoices')->insertOrIgnore($invoiceRows);
        }

        $this->command?->info('Seeding 150,000 journal entries and 300,000 journal lines...');
        $entryRows = [];
        $lineRows = [];
        $entryCount = 150000;
        $entryIdStart = (int) (DB::table('journal_entries')->max('id') ?? 0) + 1;
        for ($i = 0; $i < $entryCount; $i++) {
            $eid = $entryIdStart + $i;
            $date = $now->copy()->subDays($i % 365)->toDateString();
            $entryRows[] = [
                'id' => $eid,
                'entry_no' => 'PJV-'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'entry_date' => $date,
                'voucher_type' => VoucherType::Journal->value,
                'reference_type' => null,
                'reference_id' => null,
                'narration' => 'Performance seed',
                'status' => JournalEntryStatus::Posted->value,
                'created_by' => $createdBy,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $lineRows[] = [
                'journal_entry_id' => $eid,
                'account_id' => $debitAccount,
                'debit' => 100.00,
                'credit' => 0,
                'student_id' => null,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $lineRows[] = [
                'journal_entry_id' => $eid,
                'account_id' => $creditAccount,
                'debit' => 0,
                'credit' => 100.00,
                'student_id' => null,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($entryRows) === 2000) {
                DB::table('journal_entries')->insertOrIgnore($entryRows);
                DB::table('journal_lines')->insert($lineRows);
                $entryRows = [];
                $lineRows = [];
            }
        }
        if ($entryRows) {
            DB::table('journal_entries')->insertOrIgnore($entryRows);
            DB::table('journal_lines')->insert($lineRows);
        }

        $this->command?->info('Performance seeding complete.');
        $this->command?->info('Students: ~5000, Invoices: 100000, Journal entries: 150000, Journal lines: 300000');
        $this->command?->warn('Measure page load times via: php artisan performance:benchmark (if added) or by profiling key pages/queries with Debugbar after seeding.');
    }
}
