<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->boolean('is_group')->default(false);
            $table->boolean('is_cash_or_bank')->default(false);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('type');
            $table->index('status');
            $table->index('is_cash_or_bank');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('entry_date');
            $table->string('voucher_type', 20);
            $table->nullableMorphs('reference');
            $table->text('narration')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index('entry_date');
            $table->index(['voucher_type', 'entry_date']);
            $table->index('status');
            $table->index('created_by');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->index('journal_entry_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
