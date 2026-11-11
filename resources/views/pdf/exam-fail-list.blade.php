<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fail List — {{ $exam->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; }
        table.lines th { background: #f3f3f3; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institute['name'] }}</h1>
        <p>Fail List — {{ $exam->name }} ({{ $exam->batch?->name }})</p>
    </div>
    <table class="lines">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Student</th>
                <th>GPA</th>
                <th>Failed subjects</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->student?->admission_no }}</td>
                    <td>{{ $row->student?->name }}</td>
                    <td>{{ number_format((float) $row->gpa, 2) }}</td>
                    <td>
                        {{ $row->items->where('is_pass', false)->map(fn ($i) => $i->examSubject?->subject?->name)->filter()->implode(', ') }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No failing students.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
