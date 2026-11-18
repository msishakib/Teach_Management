<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Concerns\LogsActivityWithContext;
use App\Models\Scopes\PortalStudentOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, LogsActivityWithContext;

    protected static function booted(): void
    {
        static::addGlobalScope(new PortalStudentOwnedScope(hideFromTeachers: true));
    }

    protected $fillable = [
        'order_no',
        'student_id',
        'user_id',
        'course_id',
        'amount',
        'discount',
        'coupon_id',
        'net_amount',
        'status',
        'gateway',
        'gateway_txn_id',
        'paid_at',
        'idempotency_key',
        'manual_trx_id',
        'manual_method',
        'enrollment_id',
        'receipt_id',
        'journal_entry_id',
        'refund_reason',
        'refunded_at',
        'refunded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => OrderStatus::class,
            'gateway' => PaymentGateway::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderByDesc('id');
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
}
