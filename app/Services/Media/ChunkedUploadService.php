<?php

namespace App\Services\Media;

use App\Enums\MediaCategory;
use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Minimal tus-style chunked upload manager.
 *
 * Chunks live on a local disk until the upload is completed, at which point
 * they are concatenated and handed to {@see MediaService} for validation and
 * storage. This keeps large video uploads resumable and independent of the
 * web server's request size limits.
 */
class ChunkedUploadService
{
    public function __construct(protected MediaService $media)
    {
    }

    protected function disk(): string
    {
        return (string) config('media.chunk.disk', 'local');
    }

    protected function root(): string
    {
        return trim((string) config('media.chunk.directory', 'media-chunks'), '/');
    }

    protected function directory(string $uploadId): string
    {
        return $this->root().'/'.$uploadId;
    }

    /**
     * Start a new upload session.
     *
     * @return array{upload_id: string, received: list<int>}
     */
    public function initiate(MediaCategory $category, string $originalName, int $totalSize, Authenticatable|int|null $uploader = null): array
    {
        if ($category->type() !== MediaType::Video || $totalSize > $category->maxSizeBytes()) {
            throw ValidationException::withMessages([
                'total_size' => 'The selected category or upload size is not allowed.',
            ]);
        }

        $uploadId = (string) Str::uuid();
        $uploadedBy = $uploader instanceof Authenticatable ? $uploader->getAuthIdentifier() : $uploader;

        if ($uploadedBy === null) {
            throw ValidationException::withMessages(['upload' => 'An authenticated uploader is required.']);
        }

        Storage::disk($this->disk())->makeDirectory($this->directory($uploadId));

        $this->writeMeta($uploadId, [
            'category' => $category->value,
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'uploaded_by' => $uploadedBy,
            'created_at' => now()->toIso8601String(),
        ]);

        return ['upload_id' => $uploadId, 'received' => []];
    }

    /**
     * Store a single chunk (idempotent — re-sending a chunk overwrites it).
     */
    public function appendChunk(string $uploadId, int $index, UploadedFile $chunk, Authenticatable|int $uploader): void
    {
        $meta = $this->meta($uploadId, $uploader);

        if ($index < 0 || ! $chunk->isValid()) {
            throw ValidationException::withMessages(['chunk' => 'Invalid chunk index.']);
        }

        $directory = $this->directory($uploadId);
        $chunkPath = $directory.'/'.$this->chunkName($index);
        $disk = Storage::disk($this->disk());
        $currentSize = $disk->exists($chunkPath) ? $disk->size($chunkPath) : 0;
        $uploadedSize = collect($disk->files($directory))
            ->reject(fn (string $path): bool => $path === $chunkPath || basename($path) === 'meta.json')
            ->sum(fn (string $path): int => (int) $disk->size($path));

        if ($uploadedSize - $currentSize + (int) $chunk->getSize() > (int) $meta['total_size']) {
            throw ValidationException::withMessages([
                'chunk' => 'The uploaded chunks exceed the declared file size.',
            ]);
        }

        Storage::disk($this->disk())->putFileAs(
            $directory,
            $chunk,
            $this->chunkName($index),
        );
    }

    /** @return array{received: list<int>, complete: bool} */
    public function status(string $uploadId, Authenticatable|int $uploader): array
    {
        $meta = $this->meta($uploadId, $uploader);
        $received = $this->receivedIndexes($uploadId);
        $continuous = $received !== [] && $received === range(0, count($received) - 1);
        $size = collect(Storage::disk($this->disk())->files($this->directory($uploadId)))
            ->reject(fn (string $path): bool => basename($path) === 'meta.json')
            ->sum(fn (string $path): int => (int) Storage::disk($this->disk())->size($path));

        return [
            'received' => $received,
            'complete' => $continuous && $size === (int) $meta['total_size'],
        ];
    }

    /**
     * Concatenate all received chunks and store the resulting media.
     */
    public function complete(string $uploadId, Model $model, Authenticatable|int $uploader): Media
    {
        $meta = $this->meta($uploadId, $uploader);
        $category = MediaCategory::from($meta['category']);

        if (! method_exists($model, 'mediaCategories') || ! in_array($category, $model->mediaCategories(), true)) {
            throw ValidationException::withMessages([
                'category' => 'This model does not accept the selected media category.',
            ]);
        }

        $indexes = $this->receivedIndexes($uploadId);

        if ($indexes === [] || $indexes !== range(0, count($indexes) - 1)) {
            throw ValidationException::withMessages(['chunk' => 'Upload every chunk in order before completing.']);
        }

        $assembledPath = tempnam(sys_get_temp_dir(), 'media-upload-');

        if (! is_string($assembledPath)) {
            throw new RuntimeException('A temporary upload file could not be created.');
        }

        $handle = fopen($assembledPath, 'wb');

        if (! is_resource($handle)) {
            @unlink($assembledPath);
            throw new RuntimeException('The temporary upload file could not be opened.');
        }

        try {
            foreach ($indexes as $index) {
                $input = Storage::disk($this->disk())->readStream(
                    $this->directory($uploadId).'/'.$this->chunkName($index),
                );

                if (! is_resource($input)) {
                    throw new RuntimeException('An uploaded chunk could not be read.');
                }

                try {
                    stream_copy_to_stream($input, $handle);
                } finally {
                    fclose($input);
                }
            }

            fclose($handle);
            $handle = null;

            if (filesize($assembledPath) !== (int) $meta['total_size']) {
                throw ValidationException::withMessages([
                    'chunk' => 'The assembled upload does not match the declared file size.',
                ]);
            }

            $file = new UploadedFile(
                $assembledPath,
                (string) ($meta['original_name'] ?? 'upload'),
                null,
                null,
                true,
            );

            $media = $this->media->store($file, $model, $category, $meta['uploaded_by']);
            Storage::disk($this->disk())->deleteDirectory($this->directory($uploadId));

            return $media;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($assembledPath);
        }
    }

    /**
     * @return list<int>
     */
    protected function receivedIndexes(string $uploadId): array
    {
        $files = Storage::disk($this->disk())->files($this->directory($uploadId));

        $indexes = [];

        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match('/^(\d+)\.part$/', $name, $matches) === 1) {
                $indexes[] = (int) $matches[1];
            }
        }

        sort($indexes);

        return $indexes;
    }

    protected function chunkName(int $index): string
    {
        return str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(string $uploadId, Authenticatable|int $uploader): array
    {
        $this->assertExists($uploadId);

        $raw = Storage::disk($this->disk())->get($this->directory($uploadId).'/meta.json');
        $meta = json_decode($raw, true) ?: [];

        $uploaderId = $uploader instanceof Authenticatable ? $uploader->getAuthIdentifier() : $uploader;

        abort_unless((string) ($meta['uploaded_by'] ?? '') === (string) $uploaderId, 404);

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function writeMeta(string $uploadId, array $meta): void
    {
        Storage::disk($this->disk())->put(
            $this->directory($uploadId).'/meta.json',
            json_encode($meta),
        );
    }

    protected function assertExists(string $uploadId): void
    {
        if (! Storage::disk($this->disk())->exists($this->directory($uploadId))) {
            throw ValidationException::withMessages(['upload' => 'This upload session no longer exists.']);
        }
    }
}
