<?php

namespace App\Services;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Admin-managed public navigation. Rows are cached once for all locations;
 * links are resolved per request because they depend on locale and current page.
 */
class MenuService
{
    public const CACHE_KEY = 'public.menu';

    public const TTL_MINUTES = 60;

    /**
     * @return array<int, array{label: string, href: string, new_tab: bool, current: bool}>
     */
    public function links(MenuLocation $location): array
    {
        $onHome = request()->routeIs('home');

        return collect($this->rows()[$location->value] ?? [])
            ->map(function (array $row) use ($onHome): ?array {
                $href = $this->href($row, $onHome);

                if ($href === null) {
                    return null;
                }

                $label = app()->getLocale() === 'bn' && filled($row['label_bn']) ? $row['label_bn'] : $row['label'];

                return [
                    'label' => $label,
                    'href' => $href,
                    'new_tab' => $row['new_tab'],
                    'current' => $row['type'] === MenuLinkType::Page->value && request()->routeIs($row['target']),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Accepts absolute http(s) URLs, site-relative paths and anchors; rejects `javascript:` etc.
     */
    public static function isSafeCustomUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        if (Str::startsWith($url, ['#', '/']) && ! Str::startsWith($url, '//')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }

    /**
     * @return array<string, array<int, array{label: string, label_bn: ?string, type: string, target: string, new_tab: bool}>>
     */
    protected function rows(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::TTL_MINUTES), fn () => MenuItem::query()
            ->active()
            ->ordered()
            ->get(['location', 'label', 'label_bn', 'link_type', 'target', 'open_in_new_tab'])
            ->groupBy(fn (MenuItem $item) => $item->location->value)
            ->map(fn ($items) => $items->map(fn (MenuItem $item): array => [
                'label' => $item->label,
                'label_bn' => $item->label_bn,
                'type' => $item->link_type->value,
                'target' => $item->target,
                'new_tab' => $item->open_in_new_tab,
            ])->values()->all())
            ->all());
    }

    /**
     * @param  array{type: string, target: string}  $row
     */
    protected function href(array $row, bool $onHome): ?string
    {
        $type = MenuLinkType::tryFrom($row['type']);

        return match ($type) {
            MenuLinkType::Section => array_key_exists($row['target'], $type->targets())
                ? ($onHome ? '' : route('home')).'#'.$row['target']
                : null,
            MenuLinkType::Page => array_key_exists($row['target'], $type->targets())
                ? route($row['target'])
                : null,
            MenuLinkType::Custom => self::isSafeCustomUrl($row['target']) ? $row['target'] : null,
            default => null,
        };
    }
}
