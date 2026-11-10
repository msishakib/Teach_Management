<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('debit_total', 15, 2)->default(0);
            $table->decimal('credit_total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'date']);
            $table->index('date');
            $table->index(['date', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances_daily');
    }
};
