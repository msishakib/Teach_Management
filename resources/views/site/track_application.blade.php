<!doctype html>
<html>
<body>
    <h1>Track Application</h1>
    @if(! isset($application))
        <form method="post" action="{{ route('admissions.track', ['locale' => app()->getLocale()]) }}">
            @csrf
            <label>Application No <input name="application_no" required></label>
            <label>Phone <input name="phone" required></label>
            <button type="submit">Track</button>
        </form>
    @else
        <p>Application: {{ $application->application_no }}</p>
        <p>Name: {{ $application->name }}</p>
        <p>Status: {{ $application->status }}</p>
        <p>Remarks: {{ $application->remarks }}</p>
    @endif
</body>
</html>

