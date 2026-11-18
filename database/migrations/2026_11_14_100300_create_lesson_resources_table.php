<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();

            $table->index(['lesson_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
    }
};
