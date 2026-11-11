<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language from the `{locale}` URL prefix to public pages, so every
 * language has its own crawlable URL. The choice is remembered in a cookie for `/`.
 */
class SetPublicLocale
{
    public const SUPPORTED = ['en', 'bn'];

    public const COOKIE = 'locale';

    public static function pattern(): string
    {
        return implode('|', self::SUPPORTED);
    }

    /**
     * Locale to send a visitor to when the URL has none: cookie, then browser, then default.
     */
    public static function preferred(Request $request): string
    {
        $cookie = $request->cookie(self::COOKIE);

        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        $default = config('app.locale');
        $default = in_array($default, self::SUPPORTED, true) ? $default : self::SUPPORTED[0];

        if ($request->headers->has('Accept-Language')) {
            return $request->getPreferredLanguage(self::SUPPORTED) ?? $default;
        }

        return $default;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $locale = $route?->parameter('locale');

        if (! is_string($locale) || ! in_array($locale, self::SUPPORTED, true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        // Controllers receive route parameters positionally, so the prefix must not shift them.
        $route->forgetParameter('locale');

        if ($request->cookie(self::COOKIE) !== $locale) {
            Cookie::queue(self::COOKIE, $locale, 60 * 24 * 365);
        }

        return $next($request);
    }
}
