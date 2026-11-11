<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at');
            $table->json('snapshot')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'version']);
            $table->index('exam_id');
            $table->index('processed_by');
            $table->index('processed_at');
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('result_version_id')->nullable()->constrained('result_versions')->nullOnDelete();
            $table->decimal('total_marks', 10, 2)->default(0);
            $table->decimal('full_marks', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('gpa', 3, 2)->default(0);
            $table->string('letter_grade', 10)->nullable();
            $table->string('status', 20)->default('fail');
            $table->unsignedInteger('rank_in_batch')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index('exam_id');
            $table->index('student_id');
            $table->index('result_version_id');
            $table->index('status');
            $table->index('rank_in_batch');
            $table->index('published_at');
        });

        Schema::create('exam_result_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_result_id')->constrained('exam_results')->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->restrictOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->string('letter_grade', 10)->nullable();
            $table->decimal('grade_point', 3, 2)->default(0);
            $table->boolean('is_pass')->default(false);
            $table->boolean('is_absent')->default(false);
            $table->timestamps();

            $table->unique(['exam_result_id', 'exam_subject_id']);
            $table->index('exam_result_id');
            $table->index('exam_subject_id');
            $table->index('is_pass');
        });

        if (! Schema::hasColumn('guardians', 'user_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('user_id');
            });
        }

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->string('certificate_no')->unique();
            $table->string('verify_token', 64)->unique();
            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id');
            $table->index('course_id');
            $table->index('exam_id');
            $table->index('issued_by');
            $table->index('issued_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('exam_result_items');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('result_versions');

        if (Schema::hasColumn('guardians', 'user_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
