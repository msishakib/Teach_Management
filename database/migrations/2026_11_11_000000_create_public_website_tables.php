<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_bn')->nullable();
            $table->text('subtitle')->nullable();
            $table->text('subtitle_bn')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_label_bn', 60)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->text('quote_bn')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->string('question_bn')->nullable();
            $table->text('answer');
            $table->text('answer_bn')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_bn')->nullable();
            $table->string('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Public-facing extras for courses/teachers live in satellite tables so
        // the operational tables (and their admin resources) stay untouched.
        Schema::create('course_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category', 60)->nullable();
            $table->string('tagline')->nullable();
            $table->string('duration', 60)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('price_period', 20)->default('monthly');
            $table->string('image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_featured', 'sort_order']);
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subject', 120)->nullable();
            $table->string('qualification')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_featured', 'sort_order']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('contact');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'read_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('course_profiles');
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('banners');
    }
};
