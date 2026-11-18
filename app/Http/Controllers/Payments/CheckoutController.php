<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Payments\CourseOrderService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        Course $course,
        CourseOrderService $orders,
        PaymentGatewayManager $gateways,
    ): View|RedirectResponse {
        abort_unless($course->is_active && $course->is_published, 404);
        abort_unless($course->hasOnlineComponent(), 404);

        $amount = $orders->serverPrice($course);

        if (bccomp($amount, '0', 2) <= 0) {
            return redirect()->route('courses.show', ['course' => $course->code]);
        }

        $drivers = collect(PaymentGateway::selectable())
            ->mapWithKeys(fn (PaymentGateway $g) => [$g->value => $g->getLabel()])
            ->all();

        $sessionKey = 'checkout_idempotency.'.$course->id;
        if (! $request->session()->has($sessionKey)) {
            $request->session()->put($sessionKey, (string) \Illuminate\Support\Str::uuid());
        }

        return view('site.payments.checkout', [
            'course' => $course,
            'amount' => $amount,
            'drivers' => $drivers,
            'defaultGateway' => $gateways->defaultDriver(),
            'idempotencyKey' => (string) $request->session()->get($sessionKey),
        ]);
    }

    public function store(
        Request $request,
        Course $course,
        CourseOrderService $orders,
    ): RedirectResponse {
        abort_unless($course->is_active && $course->is_published, 404);
        abort_unless($course->hasOnlineComponent(), 404);

        $data = $request->validate([
            'gateway' => ['required', 'string', Rule::in(array_map(
                fn (PaymentGateway $g) => $g->value,
                PaymentGateway::selectable(),
            ))],
            'coupon' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $request->session()->put('checkout_idempotency.'.$course->id, $data['idempotency_key']);

        $result = $orders->checkout(
            user: $request->user(),
            course: $course,
            gateway: $data['gateway'],
            couponCode: $data['coupon'] ?? null,
            idempotencyKey: $data['idempotency_key'],
        );

        $initiation = $result['initiation'];

        if ($initiation->redirectUrl) {
            return redirect()->away($initiation->redirectUrl);
        }

        return redirect()
            ->route('payments.manual.show', ['order' => $result['order']->order_no]);
    }
}
