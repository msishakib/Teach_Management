<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate verification</title>
    <style>
        body { font-family: Georgia, serif; background: #f7f4ef; color: #1c1917; margin: 0; padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #fff; padding: 28px; border: 1px solid #e7e5e4; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        .ok { color: #166534; }
        .bad { color: #991b1b; }
        dt { font-weight: bold; margin-top: 12px; }
        dd { margin: 4px 0 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Certificate verification</h1>
        @if ($certificate)
            <p class="ok">This certificate is valid.</p>
            <dl>
                <dt>Name</dt>
                <dd>{{ $certificate->student?->name }}</dd>
                <dt>Course</dt>
                <dd>{{ $certificate->course?->name }}</dd>
                <dt>Date</dt>
                <dd>{{ $certificate->issued_on?->format('d M Y') }}</dd>
                <dt>Certificate No</dt>
                <dd>{{ $certificate->certificate_no }}</dd>
            </dl>
        @else
            <p class="bad">No certificate was found for this reference.</p>
        @endif
    </div>
</body>
</html>
