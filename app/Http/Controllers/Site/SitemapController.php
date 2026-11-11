<?php

namespace App\Http\Controllers\Site;

use App\Enums\NoticeAudience;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetPublicLocale;
use App\Models\Notice;
use App\Services\HomepageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember(
            HomepageService::SITEMAP_KEY,
            now()->addMinutes(HomepageService::TTL_MINUTES),
            fn () => $this->buildSitemap(),
        );

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /teacher',
            'Disallow: /student',
            'Disallow: /lang/',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    protected function buildSitemap(): string
    {
        $pages = [
            ['route' => 'home', 'params' => [], 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => null],
            ['route' => 'apply', 'params' => [], 'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => null],
            ['route' => 'notices.index', 'params' => [], 'priority' => '0.7', 'changefreq' => 'daily', 'lastmod' => null],
            ['route' => 'gallery', 'params' => [], 'priority' => '0.5', 'changefreq' => 'monthly', 'lastmod' => null],
        ];

        Notice::query()
            ->published()
            ->where('audience', NoticeAudience::All->value)
            ->latest('published_at')
            ->limit(500)
            ->get(['id', 'title', 'updated_at'])
            ->each(function (Notice $notice) use (&$pages): void {
                $pages[] = [
                    'route' => 'notices.show',
                    'params' => ['notice' => $notice->id, 'slug' => $notice->slug()],
                    'priority' => '0.6',
                    'changefreq' => 'yearly',
                    'lastmod' => $notice->updated_at?->toAtomString(),
                ];
            });

        $body = collect($pages)->flatMap(function (array $page): array {
            $urls = collect(SetPublicLocale::SUPPORTED)
                ->mapWithKeys(fn (string $locale) => [$locale => route($page['route'], [...$page['params'], 'locale' => $locale])]);

            $links = $urls
                ->map(fn (string $url, string $locale) => '<xhtml:link rel="alternate" hreflang="'.$locale.'" href="'.e($url).'"/>')
                ->push('<xhtml:link rel="alternate" hreflang="x-default" href="'.e(url('/')).'"/>')
                ->implode('');
            $lastmod = $page['lastmod'] ? "<lastmod>{$page['lastmod']}</lastmod>" : '';

            return $urls->map(fn (string $url) => '<url><loc>'.e($url)."</loc>{$links}{$lastmod}<changefreq>{$page['changefreq']}</changefreq><priority>{$page['priority']}</priority></url>")
                ->values()
                ->all();
        })->implode("\n");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n"
            .$body."\n</urlset>\n";
    }
}
