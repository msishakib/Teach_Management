<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });

        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('is_default');
        });

        Schema::create('grading_scale_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('letter_grade', 10);
            $table->decimal('grade_point', 3, 2);
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->index('grading_scale_id');
            $table->index(['grading_scale_id', 'min_percent', 'max_percent'], 'grading_scale_rules_range_index');
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('exam_type_id')->constrained('exam_types')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->foreignId('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->timestamps();

            $table->index('academic_session_id');
            $table->index('exam_type_id');
            $table->index('batch_id');
            $table->index('grading_scale_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->date('exam_date');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('full_marks', 8, 2);
            $table->decimal('pass_marks', 8, 2);
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id']);
            $table->index('subject_id');
            $table->index('teacher_id');
            $table->index('exam_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('grading_scale_rules');
        Schema::dropIfExists('grading_scales');
        Schema::dropIfExists('exam_types');
    }
};
