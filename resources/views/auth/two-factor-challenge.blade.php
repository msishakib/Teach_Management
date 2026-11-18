<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-factor verification</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            padding: 2rem;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 20px 45px rgba(15, 23, 42, .12);
        }
        h1 { margin: 0 0 .5rem; font-size: 1.35rem; }
        p { margin: 0 0 1.25rem; color: #475569; line-height: 1.5; }
        label { display: block; margin-bottom: .5rem; font-weight: 600; font-size: .9rem; }
        input {
            width: 100%;
            padding: .75rem .9rem;
            border: 1px solid #cbd5e1;
            border-radius: .6rem;
            font-size: 1.25rem;
            letter-spacing: .3em;
            text-align: center;
        }
        button {
            width: 100%;
            margin-top: 1rem;
            padding: .75rem 1rem;
            border: 0;
            border-radius: .6rem;
            background: #d97706;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .error {
            margin-bottom: 1rem;
            padding: .75rem;
            border-radius: .6rem;
            background: #fef2f2;
            color: #b91c1c;
            font-size: .9rem;
        }
        .hint { margin-top: 1rem; font-size: .8rem; color: #64748b; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .card { background: #1e293b; }
            p, .hint { color: #94a3b8; }
            input { background: #0f172a; color: #e2e8f0; border-color: #475569; }
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Two-factor verification</h1>
        <p>Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('two-factor.challenge.verify') }}">
            @csrf
            <label for="code">Verification code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus required maxlength="20">
            <button type="submit">Verify and sign in</button>
        </form>

        <p class="hint">Lost your authenticator? Use a recovery code or contact an administrator.</p>
    </main>
</body>
</html>
