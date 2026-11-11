<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Merit List — {{ $exam->name }}</title>
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
        <p>Merit List — {{ $exam->name }} ({{ $exam->batch?->name }})</p>
    </div>
    <table class="lines">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Admission No</th>
                <th>Student</th>
                <th class="right">Total</th>
                <th class="right">%</th>
                <th class="right">GPA</th>
                <th>Grade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->rank_in_batch }}</td>
                    <td>{{ $row->student?->admission_no }}</td>
                    <td>{{ $row->student?->name }}</td>
                    <td class="right">{{ number_format((float) $row->total_marks, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->percentage, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->gpa, 2) }}</td>
                    <td>{{ $row->letter_grade }}</td>
                    <td>{{ $row->status->getLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
