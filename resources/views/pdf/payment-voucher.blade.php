<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Voucher {{ $voucher->voucher_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0 0 4px; font-size: 20px; }
        .header p { margin: 2px 0; color: #444; }
        .meta { width: 100%; margin-bottom: 16px; }
        .meta td { vertical-align: top; padding: 3px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        table.lines th { background: #f3f3f3; }
        .amount { text-align: right; }
        .footer { margin-top: 28px; font-size: 11px; color: #555; }
        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 33%; text-align: center; padding-top: 36px; border-top: 1px solid #999; }
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
        <p><strong>Payment Voucher</strong></p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Voucher No:</strong> {{ $voucher->voucher_no }}<br>
                <strong>Date:</strong> {{ $voucher->date->format('d M Y') }}<br>
                <strong>Status:</strong> {{ $voucher->status->getLabel() }}<br>
                <strong>Method:</strong> {{ $voucher->method->getLabel() }}
            </td>
            <td>
                <strong>Payee:</strong>
                {{ $voucher->payee->name ?? ($voucher->teacherSalary?->teacher?->name ?? '—') }}<br>
                <strong>Expense account:</strong> {{ $voucher->expenseAccount?->code }} — {{ $voucher->expenseAccount?->name }}<br>
                <strong>Paid from:</strong> {{ $voucher->paidFromAccount?->code }} — {{ $voucher->paidFromAccount?->name }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Particulars</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $voucher->narration ?: ($voucher->expenseAccount?->name ?? 'Payment') }}</td>
                <td class="amount">{{ $institute['currency'] }}{{ number_format((float) $voucher->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($voucher->teacherSalary)
        <p class="footer">
            Salary month: {{ $voucher->teacherSalary->month }}
            · Basic {{ $institute['currency'] }}{{ number_format((float) $voucher->teacherSalary->amount, 2) }}
            · Bonus {{ $institute['currency'] }}{{ number_format((float) $voucher->teacherSalary->bonus, 2) }}
            · Deductions {{ $institute['currency'] }}{{ number_format((float) $voucher->teacherSalary->deductions, 2) }}
            · Net {{ $institute['currency'] }}{{ number_format((float) $voucher->teacherSalary->net, 2) }}
        </p>
    @endif

    <table class="signatures">
        <tr>
            <td>Prepared by<br>{{ $voucher->createdBy?->name }}</td>
            <td>Approved by<br>{{ $voucher->approvedBy?->name }}</td>
            <td>Received by</td>
        </tr>
    </table>
</body>
</html>
