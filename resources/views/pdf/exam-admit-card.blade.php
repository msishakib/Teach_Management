<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admit Card — {{ $student->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        .header h2 { margin: 8px 0 4px; font-size: 15px; }
        .header p { margin: 2px 0; color: #444; }
        .meta { width: 100%; margin-bottom: 14px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f3f3; }
        .note { margin-top: 16px; font-size: 11px; }
        .sign { margin-top: 36px; width: 100%; }
        .sign td { text-align: center; padding-top: 28px; }
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
        <h2>Admit Card</h2>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Student:</strong> {{ $student->name }}<br>
                <strong>Admission No:</strong> {{ $student->admission_no }}<br>
                <strong>Batch:</strong> {{ $exam->batch?->name }}
                @if ($exam->batch?->course) ({{ $exam->batch->course->name }})@endif
            </td>
            <td>
                <strong>Exam:</strong> {{ $exam->name }}<br>
                <strong>Type:</strong> {{ $exam->examType?->name }}<br>
                <strong>Session:</strong> {{ $exam->academicSession?->name }}<br>
                <strong>Period:</strong> {{ $exam->start_date?->format('d M Y') }} – {{ $exam->end_date?->format('d M Y') }}
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
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->subject?->name }}</td>
                    <td>{{ $row->exam_date?->format('d M Y') }}</td>
                    <td>{{ \Illuminate\Support\Str::of($row->start_time)->substr(0, 5) }}</td>
                    <td>{{ $row->duration_minutes }} min</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="note">
        Bring this admit card and a valid photo ID to every exam sitting. Electronic devices are not allowed in the hall.
    </div>

    <table class="sign">
        <tr>
            <td>_________________________<br>Student signature</td>
            <td>_________________________<br>Controller of Examinations</td>
        </tr>
    </table>
</body>
</html>
