<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receive_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->date('date');
            $table->nullableMorphs('payer');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('received_to_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 20);
            $table->string('attachment')->nullable();
            $table->text('narration')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('status');
            $table->index('account_id');
            $table->index('received_to_account_id');
            $table->index('created_by');
            $table->index('approved_by');
            $table->index('journal_entry_id');
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receive_vouchers');
    }
};
