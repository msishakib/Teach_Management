<!doctype html>
<html>
<body>
    <h1>Application submitted</h1>
    <p>Your application number: {{ $application->application_no }}</p>
    <p>Status: {{ $application->status }}</p>
    <a href="{{ route('admissions.track.form', ['locale' => app()->getLocale(), 'type' => 'admission', 'number' => $application->application_no]) }}">Track application</a>
</body>
</html>

