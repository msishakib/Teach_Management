<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Subject Stats — {{ $exam->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; }
        table.lines th { background: #f3f3f3; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institute['name'] }}</h1>
        <p>Subject-wise report — {{ $exam->name }}</p>
    </div>
    <table class="lines">
        <thead>
            <tr>
                <th>Subject</th>
                <th class="right">Highest</th>
                <th class="right">Average</th>
                <th class="right">Pass rate</th>
                <th class="right">Passed / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stats as $row)
                <tr>
                    <td>{{ $row['subject']->subject?->name }}</td>
                    <td class="right">{{ number_format($row['stats']['highest'], 2) }}</td>
                    <td class="right">{{ number_format($row['stats']['average'], 2) }}</td>
                    <td class="right">{{ number_format($row['stats']['pass_rate'], 2) }}%</td>
                    <td class="right">{{ $row['stats']['passed'] }} / {{ $row['stats']['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
