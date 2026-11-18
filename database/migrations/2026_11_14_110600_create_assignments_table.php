<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('max_marks', 8, 2)->default(100);
            $table->boolean('allow_late')->default(false);
            $table->unsignedInteger('max_files')->default(3);
            $table->timestamps();

            $table->index(['course_id', 'lesson_id']);
            $table->index(['course_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
