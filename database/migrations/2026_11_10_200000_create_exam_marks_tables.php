<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_id']);
            $table->index('exam_subject_id');
            $table->index('student_id');
            $table->index('entered_by');
            $table->index('updated_by');
            $table->index('is_absent');
        });

        Schema::create('exam_mark_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_mark_id')->constrained('exam_marks')->cascadeOnDelete();
            $table->decimal('old_marks', 6, 2)->nullable();
            $table->decimal('new_marks', 6, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('exam_mark_id');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_mark_logs');
        Schema::dropIfExists('exam_marks');
    }
};
