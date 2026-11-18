<?php

namespace App\Services\Payments;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array{coupon: Coupon, discount: string, net_amount: string}
     */
    public function validateForCourse(string $code, Course $course, string $amount): array
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();

        if (! $coupon instanceof Coupon) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon code is invalid.',
            ]);
        }

        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon is inactive.',
            ]);
        }

        if ($coupon->valid_from !== null && $coupon->valid_from->isFuture()) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon is not valid yet.',
            ]);
        }

        if ($coupon->valid_until !== null && $coupon->valid_until->isPast()) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon has expired.',
            ]);
        }

        if ($coupon->course_id !== null && (int) $coupon->course_id !== (int) $course->id) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon does not apply to this course.',
            ]);
        }

        if (! $coupon->hasRemainingUses()) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon usage limit has been reached.',
            ]);
        }

        $discount = $this->calculateDiscount($coupon, $amount);
        $net = bcsub($amount, $discount, 2);

        if (bccomp($net, '0', 2) < 0) {
            $net = '0.00';
            $discount = $amount;
        }

        return [
            'coupon' => $coupon,
            'discount' => $discount,
            'net_amount' => $net,
        ];
    }

    /**
     * Atomically consume one use. Throws if the limit was hit concurrently.
     */
    public function consume(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            /** @var Coupon $locked */
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            if (! $locked->is_active || ! $locked->hasRemainingUses()) {
                throw ValidationException::withMessages([
                    'coupon' => 'Coupon usage limit has been reached.',
                ]);
            }

            if ($locked->valid_until !== null && $locked->valid_until->isPast()) {
                throw ValidationException::withMessages([
                    'coupon' => 'Coupon has expired.',
                ]);
            }

            $locked->increment('used_count');
        });
    }

    public function calculateDiscount(Coupon $coupon, string $amount): string
    {
        if ($coupon->type === CouponType::Percent) {
            $raw = bcmul($amount, bcdiv((string) $coupon->value, '100', 4), 4);

            return number_format((float) $raw, 2, '.', '');
        }

        $fixed = number_format((float) $coupon->value, 2, '.', '');

        return bccomp($fixed, $amount, 2) > 0 ? $amount : $fixed;
    }
}
