<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->string('status', 32)->default('in_progress');
            $table->unsignedInteger('attempt_no');
            $table->json('question_order')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id', 'attempt_no']);
            $table->index(['quiz_id', 'student_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
