<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Marksheet — {{ $result->student?->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        .header h2 { margin: 8px 0 4px; font-size: 14px; }
        .header p { margin: 2px 0; color: #444; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f3f3; }
        .right { text-align: right; }
        .summary { margin-top: 14px; width: 100%; }
        .grades { margin-top: 18px; width: 60%; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institute['name'] }}</h1>
        @if (! empty($institute['address']))<p>{{ $institute['address'] }}</p>@endif
        <h2>Marksheet</h2>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Student:</strong> {{ $result->student?->name }}<br>
                <strong>Admission No:</strong> {{ $result->student?->admission_no }}<br>
                <strong>Batch:</strong> {{ $result->exam?->batch?->name }}
            </td>
            <td>
                <strong>Exam:</strong> {{ $result->exam?->name }}<br>
                <strong>Type:</strong> {{ $result->exam?->examType?->name }}<br>
                <strong>Session:</strong> {{ $result->exam?->academicSession?->name }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Subject</th>
                <th class="right">Full</th>
                <th class="right">Pass</th>
                <th class="right">Obtained</th>
                <th>Grade</th>
                <th class="right">GP</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result->items as $item)
                <tr>
                    <td>{{ $item->examSubject?->subject?->name }}</td>
                    <td class="right">{{ number_format((float) $item->examSubject?->full_marks, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->examSubject?->pass_marks, 2) }}</td>
                    <td class="right">
                        @if ($item->is_absent) Absent @else {{ number_format((float) $item->marks_obtained, 2) }} @endif
                    </td>
                    <td>{{ $item->letter_grade }}</td>
                    <td class="right">{{ number_format((float) $item->grade_point, 2) }}</td>
                    <td>{{ $item->is_pass ? 'Pass' : 'Fail' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td><strong>Total:</strong> {{ number_format((float) $result->total_marks, 2) }} / {{ number_format((float) $result->full_marks, 2) }}</td>
            <td><strong>Percentage:</strong> {{ number_format((float) $result->percentage, 2) }}%</td>
            <td><strong>GPA:</strong> {{ number_format((float) $result->gpa, 2) }} ({{ $result->letter_grade }})</td>
            <td><strong>Rank:</strong> {{ $result->rank_in_batch ?? '—' }}</td>
            <td><strong>Status:</strong> {{ $result->status->getLabel() }}</td>
        </tr>
    </table>

    @if ($gradeRules->isNotEmpty())
        <table class="lines grades">
            <thead>
                <tr>
                    <th colspan="4">Grading scale</th>
                </tr>
                <tr>
                    <th>Grade</th>
                    <th>Range %</th>
                    <th>Point</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($gradeRules as $rule)
                    <tr>
                        <td>{{ $rule->letter_grade }}</td>
                        <td>{{ $rule->min_percent }} – {{ $rule->max_percent }}</td>
                        <td>{{ number_format((float) $rule->grade_point, 2) }}</td>
                        <td>{{ $rule->remark }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
