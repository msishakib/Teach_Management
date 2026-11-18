<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('order')->default(0);
            $table->string('video_type')->default('embed');
            $table->string('video_url_or_media_id')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
