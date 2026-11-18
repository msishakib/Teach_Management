<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Services\Payments\CourseOrderService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualPaymentController extends Controller
{
    public function show(
        string $order,
        CourseOrderService $orders,
        PaymentGatewayManager $gateways,
    ): View {
        $model = $orders->findByOrderNo($order);
        abort_unless($model->gateway === PaymentGateway::Manual, 404);
        abort_unless($model->user_id === auth()->id() || auth()->user()?->can('payment.update'), 403);

        $initiation = $gateways->driver('manual')->initiate($model);

        return view('site.payments.manual', [
            'order' => $model->load('course'),
            'meta' => $initiation->meta,
        ]);
    }

    public function submit(Request $request, string $order, CourseOrderService $orders): RedirectResponse
    {
        $model = $orders->findByOrderNo($order);
        abort_unless($model->gateway === PaymentGateway::Manual, 404);
        abort_unless($model->user_id === $request->user()->id, 403);
        abort_unless($model->isPending(), 404);

        $data = $request->validate([
            'trx_id' => ['required', 'string', 'max:100'],
            'method' => ['required', 'in:bkash,nagad'],
        ]);

        $model->update([
            'manual_trx_id' => $data['trx_id'],
            'manual_method' => $data['method'],
        ]);

        return redirect()
            ->route('payments.manual.submitted', ['order' => $model->order_no])
            ->with('status', 'TrxID submitted. Access will unlock after admin verification.');
    }

    public function submitted(string $order, CourseOrderService $orders): View
    {
        $model = $orders->findByOrderNo($order);

        return view('site.payments.manual-submitted', ['order' => $model->load('course')]);
    }
}
