<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetPublicLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Legacy `/lang/{locale}` switch: open the same page in the chosen language.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetPublicLocale::SUPPORTED, true), 404);

        Cookie::queue(SetPublicLocale::COOKIE, $locale, 60 * 24 * 365);

        $previous = url()->previous();
        $target = route('home', ['locale' => $locale]);

        if (parse_url($previous, PHP_URL_HOST) === $request->getHost()) {
            $path = (string) parse_url($previous, PHP_URL_PATH);
            $query = parse_url($previous, PHP_URL_QUERY);
            $localized = preg_replace('#^/('.SetPublicLocale::pattern().')(?=/|$)#', '/'.$locale, $path, 1, $count);

            if ($count === 1) {
                $target = url($localized).($query ? '?'.$query : '');
            }
        }

        return redirect()->to($target);
    }

    /**
     * `/` has no language of its own; send visitors to their preferred one.
     */
    public function root(Request $request): RedirectResponse
    {
        return redirect()->route('home', ['locale' => SetPublicLocale::preferred($request)]);
    }

    /**
     * Pre-localization URLs such as `/notices/5` keep working (and keep their ranking).
     */
    public function legacy(Request $request, string $path): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->to(
            url(SetPublicLocale::preferred($request).'/'.$path).($query ? '?'.$query : ''),
            301,
        );
    }
}
