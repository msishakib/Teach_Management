<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Identity
            $table->string('national_id', 50)->nullable()->after('gender');
            $table->string('birth_certificate_no', 50)->nullable()->after('national_id');
            $table->string('religion', 50)->nullable()->after('birth_certificate_no');
            $table->string('blood_group', 10)->nullable()->after('religion');

            // Present / permanent address (the legacy `address` column stays as a fallback)
            $table->text('present_address')->nullable()->after('address');
            $table->text('permanent_address')->nullable()->after('present_address');

            // Academic background
            $table->string('last_institution')->nullable()->after('permanent_address');
            $table->string('last_exam', 50)->nullable()->after('last_institution');
            $table->string('last_result', 50)->nullable()->after('last_exam');
            $table->unsignedSmallInteger('passing_year')->nullable()->after('last_result');

            $table->index('national_id');
            $table->index('birth_certificate_no');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['national_id']);
            $table->dropIndex(['birth_certificate_no']);
            $table->dropColumn([
                'national_id',
                'birth_certificate_no',
                'religion',
                'blood_group',
                'present_address',
                'permanent_address',
                'last_institution',
                'last_exam',
                'last_result',
                'passing_year',
            ]);
        });
    }
};
