<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->decimal('pass_percent', 5, 2)->default(60);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('one_question_per_page')->default(false);
            $table->string('show_answers_after', 32)->default('after_submit');
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'lesson_id']);
            $table->index(['course_id', 'available_from', 'available_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
