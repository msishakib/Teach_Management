<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    public function cacheKey(): string
    {
        return (string) config('settings.cache_key', 'app.settings');
    }

    /**
     * All settings as a flat map of "group.key" => value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever($this->cacheKey(), function () {
            return Setting::query()
                ->get(['group', 'key', 'value'])
                ->mapWithKeys(fn (Setting $setting) => [
                    "{$setting->group}.{$setting->key}" => $setting->value,
                ])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        if ($default !== null) {
            return $default;
        }

        return data_get(config('settings.defaults'), $key);
    }

    /**
     * Accepts either a flat map ("group.key" => value) or nested groups
     * (group => [key => value]).
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $flat = $this->flatten($values);

        DB::transaction(function () use ($flat) {
            foreach ($flat as $dotted => $value) {
                [$group, $key] = $this->splitKey($dotted);

                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value],
                );
            }
        });

        $this->forgetCache();
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());

        // The public site renders institute name, colours, stat overrides, etc.
        app(HomepageService::class)->flush();
    }

    /**
     * Seed missing defaults without overwriting existing values.
     */
    public function seedDefaults(): void
    {
        $defaults = config('settings.defaults', []);

        DB::transaction(function () use ($defaults) {
            foreach ($defaults as $group => $items) {
                foreach ($items as $key => $value) {
                    Setting::query()->firstOrCreate(
                        ['group' => $group, 'key' => $key],
                        ['value' => $value],
                    );
                }
            }
        });

        $this->forgetCache();
    }

    /**
     * Grouped settings for forms: group => [key => value].
     *
     * @return array<string, array<string, mixed>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $dotted => $value) {
            [$group, $key] = $this->splitKey($dotted);
            $grouped[$group][$key] = $value;
        }

        foreach (config('settings.defaults', []) as $group => $items) {
            foreach ($items as $key => $value) {
                $grouped[$group][$key] ??= $value;
            }
        }

        return $grouped;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitKey(string $key): array
    {
        if (! str_contains($key, '.')) {
            throw new \InvalidArgumentException("Setting key [{$key}] must be in group.key format.");
        }

        $group = str($key)->before('.')->toString();
        $settingKey = str($key)->after('.')->toString();

        if ($group === '' || $settingKey === '') {
            throw new \InvalidArgumentException("Setting key [{$key}] must be in group.key format.");
        }

        return [$group, $settingKey];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function flatten(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $firstKey = (string) array_key_first($values);

        if (str_contains($firstKey, '.')) {
            return $values;
        }

        $flat = [];

        foreach ($values as $group => $items) {
            if (! is_array($items)) {
                throw new \InvalidArgumentException("Grouped settings for [{$group}] must be an array of key/value pairs.");
            }

            foreach ($items as $key => $value) {
                $flat["{$group}.{$key}"] = $value;
            }
        }

        return $flat;
    }
}
