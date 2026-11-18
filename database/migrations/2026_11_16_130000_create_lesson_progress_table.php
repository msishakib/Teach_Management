<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'lesson_id']);
            $table->index(['student_id', 'course_id']);
            $table->index(['course_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
