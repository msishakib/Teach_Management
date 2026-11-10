<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->foreignId('taken_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['batch_id', 'subject_id', 'date']);
            $table->index(['batch_id', 'date']);
            $table->index('status');
            $table->index('teacher_id');
        });

        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id']);
            $table->index(['student_id', 'attendance_session_id']);
            $table->index('status');
        });

        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['teacher_id', 'date']);
            $table->index(['teacher_id', 'date']);
            $table->index('status');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('requester');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['from_date', 'to_date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('teacher_attendances');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('attendance_sessions');
    }
};
