<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\Payments\CourseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FakePaymentController extends Controller
{
    public function show(string $order, CourseOrderService $orders): View
    {
        abort_if(app()->environment('production'), 404);

        $model = $orders->findByOrderNo($order);
        abort_unless($model->isPending(), 404);

        return view('site.payments.fake', ['order' => $model->load('course')]);
    }

    public function pay(Request $request, string $order, CourseOrderService $orders): RedirectResponse
    {
        abort_if(app()->environment('production'), 404);

        $data = $request->validate([
            'outcome' => ['required', 'in:success,failed,cancelled'],
            'amount' => ['nullable', 'numeric'],
        ]);

        $model = $orders->findByOrderNo($order);

        if ($data['outcome'] === 'cancelled') {
            $orders->markCancelled($model);

            return redirect()->route('payments.cancelled', ['order' => $model->order_no]);
        }

        if ($data['outcome'] === 'failed') {
            $orders->markFailed($model, 'Fake gateway failure.');

            return redirect()->route('payments.failed', ['order' => $model->order_no]);
        }

        $amount = isset($data['amount'])
            ? number_format((float) $data['amount'], 2, '.', '')
            : number_format((float) $model->net_amount, 2, '.', '');

        $model = $orders->confirmFromCallback($model, [
            'status' => 'success',
            'amount' => $amount,
            'tran_id' => 'FAKE-'.$model->order_no,
        ], fromIpn: false);

        if ($model->status === OrderStatus::Paid) {
            return redirect()->route('payments.success', ['order' => $model->order_no]);
        }

        return redirect()->route('payments.failed', ['order' => $model->order_no]);
    }
}
