<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_no')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('deleted_at');
        });

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('upcoming');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique('name');
            $table->index('status');
            $table->index('is_current');
            $table->index('start_date');
            $table->index('end_date');
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index('name');
            $table->index('is_active');
            $table->index('deleted_at');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index('name');
            $table->index('is_active');
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['course_id', 'session_id', 'name']);
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });

        Schema::create('class_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['batch_id', 'subject_id', 'day', 'start_time'], 'class_routines_slot_unique');
            $table->index('day');
            $table->index('teacher_id');
            $table->index('start_time');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->date('enrolled_on');
            $table->string('status')->default('active');
            $table->timestamps();

            // One enrollment row per student/batch; status tracks active/completed/dropped.
            $table->unique(['student_id', 'batch_id']);
            $table->index('status');
            $table->index('enrolled_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('class_routines');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('students');
    }
};
