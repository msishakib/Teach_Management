<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Student due list / receivables: status+due filters and grouping by student.
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->index(['status', 'due', 'student_id'], 'student_invoices_status_due_student_id_index');
            $table->index(['student_id', 'due_date'], 'student_invoices_student_due_date_index');
        });

        // Ledger/cash/bank books: posted lines ordered by entry date.
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(['status', 'entry_date', 'id'], 'journal_entries_status_entry_date_id_index');
        });

        // Batch-scoped attendance/enrollment lookups used by reports.
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['batch_id', 'status', 'student_id'], 'enrollments_batch_status_student_index');
            $table->index(['status', 'student_id'], 'enrollments_status_student_index');
        });

        // Course progress/completion aggregations.
        // course_completions already has unique(course_id, student_id); add reverse lookup.
        Schema::table('course_completions', function (Blueprint $table): void {
            $table->index(['student_id', 'course_id'], 'course_completions_student_course_index');
        });

    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->dropIndex('student_invoices_status_due_student_id_index');
            $table->dropIndex('student_invoices_student_due_date_index');
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_entries_status_entry_date_id_index');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollments_batch_status_student_index');
            $table->dropIndex('enrollments_status_student_index');
        });

        Schema::table('course_completions', function (Blueprint $table): void {
            $table->dropIndex('course_completions_student_course_index');
        });
    }
};
