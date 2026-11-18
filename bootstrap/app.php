<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            '2fa.required' => RequireTwoFactorAuthentication::class,
            'session.timeout' => EnforceSessionTimeout::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payments/sslcommerz/*',
            'payments/bkash/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
