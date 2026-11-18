<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Receipt collection reports filter posted receipts by voucher type and date.
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(
                ['status', 'voucher_type', 'entry_date'],
                'journal_entries_status_voucher_entry_date_index',
            );
        });

        // Student statement: student + status + date range.
        Schema::table('receipts', function (Blueprint $table): void {
            $table->index(['student_id', 'status', 'date'], 'receipts_student_status_date_index');
        });

        // Batch-scoped due list and ageing reports.
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->index(['batch_id', 'status', 'due'], 'student_invoices_batch_status_due_index');
        });

        // Upcoming exam dashboard aggregation.
        Schema::table('exams', function (Blueprint $table): void {
            $table->index(['status', 'end_date'], 'exams_status_end_date_index');
        });

        // Drop the duplicate admission_no index added earlier; the unique
        // constraint on the same column already serves lookups.
        $studentIndexes = collect(Schema::getIndexes('students'))->pluck('name');

        if ($studentIndexes->contains('students_admission_no_lookup_index')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropIndex('students_admission_no_lookup_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_entries_status_voucher_entry_date_index');
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropIndex('receipts_student_status_date_index');
        });

        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->dropIndex('student_invoices_batch_status_due_index');
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->dropIndex('exams_status_end_date_index');
        });
    }
};
