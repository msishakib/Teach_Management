<?php

namespace App\Support;

use App\Http\Middleware\SetPublicLocale;

class LocalizedUrl
{
    /**
     * The current public page in another language (same route, parameters and page number).
     */
    public static function current(string $locale): string
    {
        $route = request()->route();
        $name = $route?->getName();

        if (! $name || ! in_array($name, self::pageRoutes(), true)) {
            return route('home', ['locale' => $locale]);
        }

        return route($name, [...$route->parameters(), 'locale' => $locale, ...self::pageQuery()]);
    }

    /**
     * Canonical URL of the current page (drops tracking/query noise but keeps pagination).
     */
    public static function canonical(): string
    {
        return url()->current().(self::pageQuery() ? '?'.http_build_query(self::pageQuery()) : '');
    }

    /**
     * @return array<string, string> locale => URL
     */
    public static function alternates(): array
    {
        return collect(SetPublicLocale::SUPPORTED)
            ->mapWithKeys(fn (string $locale) => [$locale => self::current($locale)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function pageRoutes(): array
    {
        return ['home', 'apply', 'notices.index', 'notices.show', 'gallery'];
    }

    /**
     * @return array<string, int>
     */
    protected static function pageQuery(): array
    {
        $page = (int) request()->query('page', 1);

        return $page > 1 ? ['page' => $page] : [];
    }
}
