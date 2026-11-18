<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\Payments\CourseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentCallbackController extends Controller
{
    public function __construct(
        protected CourseOrderService $orders,
    ) {}

    public function success(Request $request): RedirectResponse
    {
        $orderNo = (string) ($request->input('value_a')
            ?? $request->input('tran_id')
            ?? $request->input('merchantInvoiceNumber')
            ?? $request->query('order')
            ?? '');

        abort_if($orderNo === '', 404);

        $order = $this->orders->findByOrderNo($orderNo);
        $order = $this->orders->confirmFromCallback($order, $request->all(), fromIpn: false);

        if ($order->status === OrderStatus::Paid) {
            return redirect()
                ->route('payments.success', ['order' => $order->order_no]);
        }

        return redirect()
            ->route('payments.failed', ['order' => $order->order_no]);
    }

    public function fail(Request $request): RedirectResponse
    {
        $orderNo = (string) ($request->input('value_a')
            ?? $request->input('tran_id')
            ?? $request->query('order')
            ?? '');

        if ($orderNo !== '') {
            $order = $this->orders->findByOrderNo($orderNo);
            $this->orders->markFailed($order, 'Gateway reported failure.');
        }

        return redirect()->route('payments.failed', ['order' => $orderNo ?: 'unknown']);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $orderNo = (string) ($request->input('value_a')
            ?? $request->input('tran_id')
            ?? $request->query('order')
            ?? '');

        if ($orderNo !== '') {
            $order = $this->orders->findByOrderNo($orderNo);
            $this->orders->markCancelled($order);
        }

        return redirect()->route('payments.cancelled', ['order' => $orderNo ?: 'unknown']);
    }

    public function ipn(Request $request): Response
    {
        $orderNo = (string) ($request->input('value_a')
            ?? $request->input('tran_id')
            ?? $request->input('merchantInvoiceNumber')
            ?? '');

        if ($orderNo === '') {
            return response('ignored', 200);
        }

        $order = $this->orders->findByOrderNo($orderNo);
        $this->orders->confirmFromCallback($order, $request->all(), fromIpn: true);

        return response('OK', 200);
    }

    public function bkashCallback(Request $request): RedirectResponse
    {
        $orderNo = (string) ($request->input('merchantInvoiceNumber')
            ?? $request->query('order')
            ?? '');

        $paymentId = (string) $request->input('paymentID', $request->query('paymentID', ''));

        if ($orderNo === '' && $paymentId !== '') {
            $order = \App\Models\Order::query()
                ->withoutGlobalScopes()
                ->where('gateway_txn_id', $paymentId)
                ->first();

            abort_unless($order !== null, 404);
            $orderNo = $order->order_no;
        }

        abort_if($orderNo === '', 404);

        $order = $this->orders->findByOrderNo($orderNo);

        $status = strtolower((string) $request->input('status', 'success'));
        if (in_array($status, ['cancel', 'cancelled', 'failure', 'failed'], true)) {
            $this->orders->markFailed($order, 'bKash reported '.$status);

            return redirect()->route('payments.failed', ['order' => $order->order_no]);
        }

        $order = $this->orders->confirmFromCallback($order, $request->all(), fromIpn: false);

        if ($order->status === OrderStatus::Paid) {
            return redirect()->route('payments.success', ['order' => $order->order_no]);
        }

        return redirect()->route('payments.failed', ['order' => $order->order_no]);
    }

    public function showSuccess(string $order): View
    {
        $model = $this->orders->findByOrderNo($order);

        return view('site.payments.success', ['order' => $model->load(['course', 'receipt'])]);
    }

    public function showFailed(string $order): View
    {
        $model = $order !== 'unknown'
            ? $this->orders->findByOrderNo($order)
            : null;

        return view('site.payments.failed', ['order' => $model]);
    }

    public function showCancelled(string $order): View
    {
        $model = $order !== 'unknown'
            ? $this->orders->findByOrderNo($order)
            : null;

        return view('site.payments.cancelled', ['order' => $model]);
    }
}
