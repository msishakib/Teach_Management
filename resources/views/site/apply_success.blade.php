<!doctype html>
<html>
<body>
    <h1>Application submitted</h1>
    <p>Your application number: {{ $application->application_no }}</p>
    <p>Status: {{ $application->status }}</p>
    <a href="{{ route('admissions.track.form', ['locale' => app()->getLocale()]) }}">Track application</a>
</body>
</html>

