<?php

namespace App\Services\Media;

/**
 * Validates YouTube/Vimeo links and rewrites them to privacy-friendly
 * (no-cookie / no-tracking) embed URLs.
 */
class VideoEmbedService
{
    /**
     * @return array{provider: string, id: string, embed: string}|null
     */
    public function parse(string $url): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $youtubeId = $this->youtubeId($host, $path, $query);

        if ($youtubeId !== null) {
            return [
                'provider' => 'youtube',
                'id' => $youtubeId,
                'embed' => 'https://'.config('media.embed.youtube.privacy_host', 'www.youtube-nocookie.com').'/embed/'.$youtubeId,
            ];
        }

        $vimeoId = $this->vimeoId($host, $path);

        if ($vimeoId !== null) {
            return [
                'provider' => 'vimeo',
                'id' => $vimeoId,
                'embed' => 'https://'.config('media.embed.vimeo.privacy_host', 'player.vimeo.com').'/video/'.$vimeoId,
            ];
        }

        return null;
    }

    public function isValid(string $url): bool
    {
        return $this->parse($url) !== null;
    }

    public function embedUrl(string $url): ?string
    {
        return $this->parse($url)['embed'] ?? null;
    }

    public function provider(string $url): ?string
    {
        return $this->parse($url)['provider'] ?? null;
    }

    private function youtubeId(string $host, string $path, array $query): ?string
    {
        $hosts = config('media.embed.youtube.hosts', []);

        if (! in_array($host, $hosts, true) && ! in_array('www.'.$host, $hosts, true)) {
            return null;
        }

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            return $this->cleanYoutubeId($path);
        }

        // youtube.com/watch?v=ID
        if (isset($query['v'])) {
            return $this->cleanYoutubeId((string) $query['v']);
        }

        // youtube.com/embed/ID | /shorts/ID | /live/ID | /v/ID
        foreach (['embed', 'shorts', 'live', 'v'] as $segment) {
            if (str_starts_with($path, $segment.'/')) {
                return $this->cleanYoutubeId(substr($path, strlen($segment) + 1));
            }
        }

        return null;
    }

    private function cleanYoutubeId(string $id): ?string
    {
        $id = trim(strtok($id, '/&?') ?: '');

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    private function vimeoId(string $host, string $path): ?string
    {
        $hosts = config('media.embed.vimeo.hosts', []);

        if (! in_array($host, $hosts, true) && ! in_array('www.'.$host, $hosts, true)) {
            return null;
        }

        if (str_starts_with($path, 'video/')) {
            $path = substr($path, strlen('video/'));
        }

        if (preg_match('#^(\d+)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
