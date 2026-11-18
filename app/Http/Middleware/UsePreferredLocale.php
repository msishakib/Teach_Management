<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's remembered language to public pages that are not
 * prefixed with {locale} (payment gateway returns, for example). This keeps
 * localized route helpers such as route('courses.learn') working without
 * every call site having to pass the locale explicitly.
 */
class UsePreferredLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = SetPublicLocale::preferred($request);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
