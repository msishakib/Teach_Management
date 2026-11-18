<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('registration_no')->unique();

            // Personal information
            $table->string('name');
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();

            // Identity information
            $table->string('national_id', 50)->nullable();
            $table->string('birth_certificate_no', 50)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('blood_group', 10)->nullable();

            // Address
            $table->text('present_address');
            $table->text('permanent_address')->nullable();

            // Academic background
            $table->string('last_institution')->nullable();
            $table->string('last_exam', 50)->nullable();
            $table->string('last_result', 50)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();

            // Review workflow
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('phone');
            $table->index('email');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registrations');
    }
};
