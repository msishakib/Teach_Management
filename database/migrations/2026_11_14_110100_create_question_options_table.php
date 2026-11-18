<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'sort_order']);
            $table->index(['question_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
