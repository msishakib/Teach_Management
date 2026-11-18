<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = [
            'X-Frame-Options' => (string) config('security.headers.frame_options', 'SAMEORIGIN'),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if ($csp = config('security.headers.csp')) {
            $headers['Content-Security-Policy'] = (string) $csp;
        }

        if ($request->isSecure() && config('security.headers.hsts.enabled', true)) {
            $hsts = 'max-age='.(int) config('security.headers.hsts.max_age', 31536000);

            if (config('security.headers.hsts.include_sub_domains', true)) {
                $hsts .= '; includeSubDomains';
            }

            if (config('security.headers.hsts.preload', false)) {
                $hsts .= '; preload';
            }

            $headers['Strict-Transport-Security'] = $hsts;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
