<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->longText('text')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->decimal('marks', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['assignment_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
