<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Exam Routine — {{ $exam->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        .header h2 { margin: 8px 0 4px; font-size: 15px; }
        .header p { margin: 2px 0; color: #444; }
        .meta { width: 100%; margin-bottom: 16px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f3f3; }
        .footer { margin-top: 24px; font-size: 11px; color: #555; }
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
        <h2>Exam Routine</h2>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Exam:</strong> {{ $exam->name }}<br>
                <strong>Type:</strong> {{ $exam->examType?->name }}<br>
                <strong>Session:</strong> {{ $exam->academicSession?->name }}
            </td>
            <td>
                <strong>Batch:</strong> {{ $exam->batch?->name }}
                @if ($exam->batch?->course) ({{ $exam->batch->course->name }})@endif<br>
                <strong>Period:</strong> {{ $exam->start_date?->format('d M Y') }} – {{ $exam->end_date?->format('d M Y') }}<br>
                <strong>Status:</strong> {{ $exam->status->getLabel() }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Start</th>
                <th>Duration</th>
                <th>Full</th>
                <th>Pass</th>
                <th>Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->subject?->name }}</td>
                    <td>{{ $row->exam_date?->format('d M Y') }}</td>
                    <td>{{ \Illuminate\Support\Str::of($row->start_time)->substr(0, 5) }}</td>
                    <td>{{ $row->duration_minutes }} min</td>
                    <td>{{ number_format((float) $row->full_marks, 2) }}</td>
                    <td>{{ number_format((float) $row->pass_marks, 2) }}</td>
                    <td>{{ $row->teacher?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No subjects scheduled.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Printed on {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
