<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notices')) {
            Schema::create('notices', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body');
                $table->string('audience', 20)->default('all');
                $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
                $table->string('attachment')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('audience');
                $table->index('batch_id');
                $table->index('published_at');
                $table->index('expires_at');
                $table->index('is_pinned');
                $table->index('is_active');
                $table->index('created_by');
            });

            return;
        }

        Schema::table('notices', function (Blueprint $table) {
            if (! Schema::hasColumn('notices', 'attachment')) {
                $table->string('attachment')->nullable()->after('batch_id');
            }

            if (! Schema::hasColumn('notices', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('published_at');
                $table->index('expires_at');
            }

            if (! Schema::hasColumn('notices', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('expires_at');
                $table->index('is_pinned');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notices')) {
            return;
        }

        $columns = array_values(array_filter(
            ['attachment', 'expires_at', 'is_pinned'],
            fn (string $column): bool => Schema::hasColumn('notices', $column),
        ));

        if ($columns !== []) {
            Schema::table('notices', function (Blueprint $table) use ($columns) {
                if (in_array('expires_at', $columns, true)) {
                    $table->dropIndex(['expires_at']);
                }

                if (in_array('is_pinned', $columns, true)) {
                    $table->dropIndex(['is_pinned']);
                }

                $table->dropColumn($columns);
            });
        }
    }
};
