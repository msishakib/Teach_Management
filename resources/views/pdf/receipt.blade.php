<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->receipt_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0 0 4px; font-size: 20px; }
        .header p { margin: 2px 0; color: #444; }
        .meta { width: 100%; margin-bottom: 20px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f3f3; }
        .amount { text-align: right; }
        .totals { margin-top: 16px; width: 100%; }
        .totals td { padding: 4px 0; }
        .footer { margin-top: 28px; font-size: 11px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institute['name'] }}</h1>
        @if (! empty($institute['address']))
            <p>{{ $institute['address'] }}</p>
        @endif
        <p>
            @if (! empty($institute['phone'])){{ $institute['phone'] }}@endif
            @if (! empty($institute['phone']) && ! empty($institute['email'])) · @endif
            @if (! empty($institute['email'])){{ $institute['email'] }}@endif
        </p>
        <p><strong>Money Receipt</strong></p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Receipt No:</strong> {{ $receipt->receipt_no }}<br>
                <strong>Date:</strong> {{ $receipt->date->format('d M Y') }}<br>
                <strong>Method:</strong> {{ $receipt->method->getLabel() }}
                ({{ $receipt->account?->name }})
            </td>
            <td>
                <strong>Student:</strong> {{ $receipt->student?->name }}<br>
                <strong>Admission No:</strong> {{ $receipt->student?->admission_no }}<br>
                @if ($receipt->createdBy)
                    <strong>Received by:</strong> {{ $receipt->createdBy->name }}
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Month</th>
                <th class="amount">Allocated</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($receipt->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->invoice?->invoice_no }}</td>
                    <td>{{ $allocation->invoice?->month }}</td>
                    <td class="amount">{{ $institute['currency'] }}{{ number_format((float) $allocation->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Advance payment (no invoice allocation)</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td><strong>Total Received</strong></td>
            <td class="amount"><strong>{{ $institute['currency'] }}{{ number_format((float) $receipt->amount, 2) }}</strong></td>
        </tr>
    </table>

    @if ($receipt->notes)
        <p class="footer"><strong>Notes:</strong> {{ $receipt->notes }}</p>
    @endif

    <p class="footer">This is a computer-generated receipt.</p>
</body>
</html>
