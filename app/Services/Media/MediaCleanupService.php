<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Removes orphaned media records/files and stale upload chunks.
 */
class MediaCleanupService
{
    public function run(): array
    {
        return [
            'records' => $this->pruneOrphanedRecords(),
            'files' => $this->pruneOrphanedFiles(),
            'chunks' => $this->pruneTempChunks(),
        ];
    }

    /**
     * Delete media rows whose owning model no longer exists (files follow).
     */
    public function pruneOrphanedRecords(): int
    {
        $deleted = 0;

        Media::query()->with('model')->chunkById(200, function ($items) use (&$deleted): void {
            foreach ($items as $media) {
                $class = $media->model_type;

                if (! is_string($class) || ! class_exists($class)) {
                    $media->delete();
                    $deleted++;

                    continue;
                }

                if (! $class::query()->whereKey($media->model_id)->exists()) {
                    $media->delete();
                    $deleted++;
                }
            }
        });

        return $deleted;
    }

    /**
     * Delete media folders that no longer belong to a media record.
     * Only local-style disks are scanned to avoid expensive remote listings.
     */
    public function pruneOrphanedFiles(): int
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');
        $ttlHours = (int) config('media.chunk.ttl_hours', 24);
        $deleted = 0;

        foreach ($this->scannableDisks() as $disk) {
            $filesystem = Storage::disk($disk);
            $root = $prefix === '' ? null : $prefix;

            foreach ($filesystem->directories($root) as $directory) {
                $id = basename($directory);

                if (! ctype_digit($id) || Media::query()->whereKey((int) $id)->exists()) {
                    continue;
                }

                foreach ($filesystem->allFiles($directory) as $file) {
                    if (! $this->olderThan($filesystem, $file, $ttlHours)) {
                        continue;
                    }

                    $filesystem->delete($file);
                    $deleted++;
                }

                if ($filesystem->allFiles($directory) === []) {
                    $filesystem->deleteDirectory($directory);
                }
            }
        }

        return $deleted;
    }

    /**
     * Remove temporary chunk directories older than the configured TTL.
     */
    public function pruneTempChunks(): int
    {
        $disk = (string) config('media.chunk.disk', 'local');
        $directory = trim((string) config('media.chunk.directory', 'media-chunks'), '/');
        $ttlHours = (int) config('media.chunk.ttl_hours', 24);
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach ($filesystem->directories($directory) as $chunkDirectory) {
            $files = $filesystem->allFiles($chunkDirectory);

            $stale = $files !== [] && collect($files)->every(
                fn (string $file): bool => $this->olderThan($filesystem, $file, $ttlHours),
            );

            if ($stale) {
                $filesystem->deleteDirectory($chunkDirectory);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    protected function scannableDisks(): array
    {
        $disks = array_merge(
            array_values(config('media.disks', [])),
            config('media.private_disks', []),
        );

        $configured = array_keys(config('filesystems.disks', []));

        return array_values(array_unique(array_filter(
            $disks,
            fn ($disk): bool => is_string($disk)
                && in_array($disk, $configured, true)
                && config("filesystems.disks.{$disk}.driver") === 'local',
        )));
    }

    protected function olderThan($filesystem, string $file, int $hours): bool
    {
        try {
            $modified = $filesystem->lastModified($file);
        } catch (\Throwable) {
            return false;
        }

        return $modified > 0 && $modified < now()->subHours($hours)->getTimestamp();
    }
}
