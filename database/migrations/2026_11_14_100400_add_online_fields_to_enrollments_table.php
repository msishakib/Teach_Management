<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('enrolled_via')->default('offline')->after('status');
            $table->timestamp('expires_at')->nullable()->after('enrolled_via');

            $table->index('enrolled_via');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['enrolled_via']);
            $table->dropIndex(['expires_at']);

            $table->dropColumn(['enrolled_via', 'expires_at']);
        });
    }
};
