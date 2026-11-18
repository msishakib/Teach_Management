<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 20); // percent|fixed
            $table->decimal('value', 15, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_until']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 40);
            $table->string('gateway_txn_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('manual_trx_id')->nullable();
            $table->string('manual_method', 20)->nullable(); // bkash|nagad for manual
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index(['status', 'gateway']);
            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index('gateway_txn_id');
            $table->index('paid_at');
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
    }
};
