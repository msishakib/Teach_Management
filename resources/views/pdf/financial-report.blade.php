<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f3f3; }
        .num { text-align: right; }
        .meta { margin-top: 10px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ setting('general.institute_name') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ str_replace('_', ' ', ucfirst($column)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td class="{{ is_numeric($row[$column] ?? null) ? 'num' : '' }}">{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (! empty($meta))
        <div class="meta">
            @foreach ($meta as $key => $value)
                <div><strong>{{ str_replace('_', ' ', ucfirst($key)) }}:</strong> {{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
