<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate — {{ $certificate->certificate_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; }
        .frame { border: 4px solid #333; padding: 36px; text-align: center; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        h2 { font-size: 20px; margin: 24px 0 8px; font-weight: normal; }
        .name { font-size: 26px; margin: 18px 0; font-weight: bold; }
        .meta { margin-top: 28px; font-size: 12px; color: #444; }
        .qr { margin-top: 20px; }
        .qr img { width: 100px; height: 100px; }
    </style>
</head>
<body>
    <div class="frame">
        <h1>{{ $institute['name'] }}</h1>
        <p>Certificate of Completion</p>
        <h2>This is to certify that</h2>
        <div class="name">{{ $certificate->student?->name }}</div>
        <p>has successfully completed the course</p>
        <div class="name" style="font-size:20px;">{{ $certificate->course?->name }}</div>
        <p class="meta">
            Certificate No: {{ $certificate->certificate_no }}<br>
            Date: {{ $certificate->issued_on?->format('d M Y') }}
        </p>
        <div class="qr">
            <img src="{{ $qrDataUri }}" alt="Verify QR">
            <div style="font-size:10px;color:#666;">Scan to verify</div>
        </div>
    </div>
</body>
</html>
