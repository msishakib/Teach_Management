<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('text');
            $table->text('explanation')->nullable();
            $table->decimal('marks', 8, 2)->default(1);
            $table->string('difficulty', 16)->default('medium');
            $table->timestamps();

            $table->index(['course_id', 'type']);
            $table->index(['course_id', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
