<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->foreignId('exam_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('exams')
                ->nullOnDelete();

            $table->index('exam_id');
            $table->unique(['student_id', 'exam_id'], 'student_invoices_student_exam_unique');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE student_invoices MODIFY month VARCHAR(32) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE student_invoices ALTER COLUMN month TYPE VARCHAR(32)');
        } else {
            // sqlite / others: recreate is unnecessary for tests using in-memory — leave as-is;
            // month values like "exam:123" still fit when the original column is flexible enough,
            // and sqlite ignores length limits.
        }
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->dropUnique('student_invoices_student_exam_unique');
            $table->dropConstrainedForeignId('exam_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE student_invoices MODIFY month VARCHAR(7) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE student_invoices ALTER COLUMN month TYPE VARCHAR(7)');
        }
    }
};
