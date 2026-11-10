<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('name');
            $table->string('gender', 20)->nullable()->after('dob');
            $table->text('address')->nullable()->after('phone');
            $table->string('photo')->nullable()->after('address');
            $table->string('status', 30)->default('active')->after('photo');

            $table->dropUnique(['email']);
            $table->index('email');
            $table->index('phone');
            $table->index(['status', 'deleted_at']);
            $table->index(['name', 'phone']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('employee_no')->after('user_id');
            $table->date('joining_date')->nullable()->after('phone');
            $table->decimal('salary', 15, 2)->nullable()->after('joining_date');

            $table->dropUnique(['email']);
            $table->string('email')->nullable()->change();
            $table->index('email');
            $table->unique('employee_no');
            $table->index('phone');
            $table->index(['joining_date', 'deleted_at']);
            $table->index(['name', 'phone']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('phone');
            $table->index(['name', 'phone']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 50);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
            $table->index(['student_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex(['name', 'phone']);
            $table->dropIndex(['joining_date', 'deleted_at']);
            $table->dropIndex(['phone']);
            $table->dropUnique(['employee_no']);
            $table->dropIndex(['email']);
            $table->dropColumn(['employee_no', 'joining_date', 'salary']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['name', 'phone']);
            $table->dropIndex(['status', 'deleted_at']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropColumn(['dob', 'gender', 'address', 'photo', 'status']);
            $table->unique('email');
        });
    }
};
