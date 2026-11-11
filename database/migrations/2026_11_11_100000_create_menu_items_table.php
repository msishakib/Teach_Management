<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20);
            $table->string('label', 60);
            $table->string('label_bn', 60)->nullable();
            $table->string('link_type', 20);
            $table->string('target', 500);
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['location', 'is_active', 'sort_order']);
        });

        // Start with the links the site shipped with, so nothing disappears after migrating.
        $now = now();
        $rows = [];
        $defaults = [
            'header' => [
                ['section', 'courses', 'Courses', 'কোর্স'],
                ['section', 'why', 'Why us', 'কেন আমরা'],
                ['section', 'teachers', 'Teachers', 'শিক্ষক'],
                ['section', 'results', 'Results', 'ফলাফল'],
                ['section', 'notices', 'Notices', 'নোটিশ'],
                ['section', 'contact', 'Contact', 'যোগাযোগ'],
                ['page', 'gallery', 'Gallery', 'গ্যালারি'],
            ],
            'footer' => [
                ['section', 'courses', 'Courses', 'কোর্স'],
                ['section', 'teachers', 'Teachers', 'শিক্ষক'],
                ['section', 'results', 'Results', 'ফলাফল'],
                ['section', 'notices', 'Notices', 'নোটিশ'],
                ['page', 'gallery', 'Gallery', 'গ্যালারি'],
                ['section', 'faq', 'FAQ', 'প্রশ্নোত্তর'],
            ],
        ];

        foreach ($defaults as $location => $items) {
            foreach ($items as $i => [$type, $target, $label, $labelBn]) {
                $rows[] = [
                    'location' => $location,
                    'label' => $label,
                    'label_bn' => $labelBn,
                    'link_type' => $type,
                    'target' => $target,
                    'open_in_new_tab' => false,
                    'sort_order' => ($i + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('menu_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
