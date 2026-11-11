<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Presents institute identity + theme settings to the public layout, with
 * validation so admin-entered values can never break out of CSS/HTML contexts.
 */
class SiteProfileService
{
    public const DEFAULT_PRIMARY = '#4f46e5';

    public const DEFAULT_ACCENT = '#c026d3';

    /** @var array<string, mixed>|null */
    protected ?array $all = null;

    /** @var array<string, mixed>|null */
    protected ?array $profile = null;

    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->profile ??= $this->buildProfile();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProfile(): array
    {
        $address = $this->text('general.address');
        $bangla = app()->getLocale() === 'bn';
        $tagline = $bangla
            ? ($this->text('website.tagline_bn') ?? $this->text('website.tagline'))
            : $this->text('website.tagline');
        $description = $bangla
            ? ($this->text('website.meta_description_bn') ?? $tagline ?? $this->text('website.meta_description'))
            : $this->text('website.meta_description');

        return [
            'name' => $this->text('general.institute_name') ?? config('app.name'),
            'tagline' => $tagline,
            'description' => $description ?? $tagline,
            'logo' => public_image($this->text('general.logo')),
            'address' => $address,
            'phone' => $this->text('general.phone'),
            'email' => $this->text('general.email'),
            'office_hours' => $this->text('website.office_hours'),
            'currency' => $this->text('general.currency_symbol') ?? '৳',
            'primary' => $this->color('website.primary_color', self::DEFAULT_PRIMARY),
            'accent' => $this->color('website.accent_color', self::DEFAULT_ACCENT),
            'socials' => $this->socials(),
            'map_embed_url' => $this->mapUrl($address),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function socials(): array
    {
        $socials = [];

        foreach (['facebook', 'youtube', 'instagram', 'linkedin', 'twitter'] as $network) {
            $url = $this->url($this->text("website.{$network}"));

            if ($url !== null) {
                $socials[$network] = $url;
            }
        }

        $whatsapp = preg_replace('/\D+/', '', (string) $this->text('website.whatsapp'));

        if ($whatsapp !== '' && strlen($whatsapp) >= 7) {
            $socials['whatsapp'] = 'https://wa.me/'.$whatsapp;
        }

        return $socials;
    }

    protected function mapUrl(?string $address): ?string
    {
        $configured = $this->text('website.map_embed_url');

        if ($configured !== null) {
            // Accept either a raw URL or a pasted <iframe src="..."> snippet.
            if (preg_match('/src=["\']([^"\']+)["\']/i', $configured, $matches)) {
                $configured = html_entity_decode($matches[1]);
            }

            $url = $this->url($configured);

            if ($url !== null && preg_match('#^https://(www\.google\.com/maps|maps\.google\.com|www\.openstreetmap\.org/export/embed)#i', $url)) {
                return $url;
            }
        }

        if ($address !== null) {
            return 'https://www.google.com/maps?q='.rawurlencode(Str::squish($address)).'&output=embed';
        }

        return null;
    }

    protected function text(string $key): ?string
    {
        // One settings read per request (the service itself hits the cache store each call).
        $this->all ??= $this->settings->all();

        $value = array_key_exists($key, $this->all)
            ? $this->all[$key]
            : data_get(config('settings.defaults'), $key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function color(string $key, string $default): string
    {
        $value = $this->text($key);

        return ($value !== null && preg_match('/^#[0-9a-f]{6}$/i', $value)) ? strtolower($value) : $default;
    }

    protected function url(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) ? $value : null;
    }
}
