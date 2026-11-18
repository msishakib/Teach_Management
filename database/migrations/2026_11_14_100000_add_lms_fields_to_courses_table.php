<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('delivery_mode')->default('offline')->after('is_active');
            $table->boolean('is_published')->default(false)->after('delivery_mode');
            $table->decimal('price', 12, 2)->nullable()->after('is_published');
            $table->decimal('sale_price', 12, 2)->nullable()->after('price');
            $table->string('level')->nullable()->after('sale_price');
            $table->string('language')->nullable()->after('level');
            $table->string('short_description', 500)->nullable()->after('language');
            $table->text('requirements')->nullable()->after('short_description');
            $table->json('what_you_learn')->nullable()->after('requirements');
            $table->unsignedInteger('access_days')->nullable()->after('what_you_learn');

            $table->index('delivery_mode');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['delivery_mode']);
            $table->dropIndex(['is_published']);

            $table->dropColumn([
                'delivery_mode',
                'is_published',
                'price',
                'sale_price',
                'level',
                'language',
                'short_description',
                'requirements',
                'what_you_learn',
                'access_days',
            ]);
        });
    }
};
