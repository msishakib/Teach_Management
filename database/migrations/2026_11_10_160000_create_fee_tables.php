<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('income_account_id');
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('frequency', 20)->default('monthly');
            $table->timestamps();

            $table->unique(['course_id', 'fee_type_id']);
            $table->index('fee_type_id');
            $table->index('frequency');
        });

        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->string('invoice_no')->unique();
            $table->string('month', 7);
            $table->date('due_date');
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('due', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'batch_id', 'month']);
            $table->index(['batch_id', 'month']);
            $table->index(['student_id', 'status']);
            $table->index('due_date');
            $table->index('status');
            $table->index('journal_entry_id');
        });

        Schema::create('student_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('student_invoice_id');
            $table->index('fee_type_id');
            $table->index('fee_structure_id');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('method', 20);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'date']);
            $table->index('account_id');
            $table->index('journal_entry_id');
            $table->index('status');
            $table->index('method');
            $table->index('created_by');
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'student_invoice_id']);
            $table->index('student_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('student_invoice_items');
        Schema::dropIfExists('student_invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};
