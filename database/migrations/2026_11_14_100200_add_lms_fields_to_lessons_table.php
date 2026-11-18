<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->after('course_id');
            $table->string('type')->default('video')->after('title');
            $table->longText('content')->nullable()->after('type');
            $table->unsignedBigInteger('media_id')->nullable()->after('content');
            $table->string('video_url')->nullable()->after('video_url_or_media_id');
            $table->unsignedInteger('duration_seconds')->nullable()->after('duration');
            $table->boolean('is_published')->default(true)->after('is_free_preview');
            $table->unsignedInteger('sort_order')->default(0)->after('is_published');
            $table->unsignedInteger('drip_after_days')->nullable()->after('sort_order');

            $table->index('section_id');
            $table->index('media_id');
            $table->index(['section_id', 'sort_order']);

            // SQLite cannot add foreign keys to an existing table; the indexes
            // above keep the same lookup performance in tests and local dev.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->foreign('section_id')->references('id')->on('course_sections')->nullOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['section_id']);
                $table->dropForeign(['media_id']);
            }

            $table->dropIndex(['section_id']);
            $table->dropIndex(['media_id']);
            $table->dropIndex(['section_id', 'sort_order']);

            $table->dropColumn([
                'section_id',
                'type',
                'content',
                'media_id',
                'video_url',
                'duration_seconds',
                'is_published',
                'sort_order',
                'drip_after_days',
            ]);
        });
    }
};
