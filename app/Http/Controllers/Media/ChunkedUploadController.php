<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\ChunkedUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Chunked / resumable upload endpoints (tus-style). Clients initiate a
 * session, PATCH chunks in any order, then complete it.
 */
class ChunkedUploadController extends Controller
{
    public function __construct(protected ChunkedUploadService $uploads)
    {
    }

    public function initiate(Request $request): JsonResponse
    {
        Gate::authorize('upload', Media::class);
        $this->enforceRateLimit($request);

        $data = $request->validate([
            'category' => ['required', Rule::enum(MediaCategory::class)],
            'original_name' => ['required', 'string', 'max:180'],
            'total_size' => ['required', 'integer', 'min:1'],
        ]);

        $category = MediaCategory::from($data['category']);

        if ((int) $data['total_size'] > $category->maxSizeBytes()) {
            throw ValidationException::withMessages([
                'total_size' => 'The file may not be larger than '.round($category->maxSizeBytes() / 1024).' KB.',
            ]);
        }

        $session = $this->uploads->initiate(
            $category,
            $data['original_name'],
            (int) $data['total_size'],
            $request->user(),
        );

        return response()->json($session, 201);
    }

    public function append(Request $request, string $uploadId): JsonResponse
    {
        Gate::authorize('upload', Media::class);

        $this->enforceRateLimit($request, true);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        $this->uploads->appendChunk($uploadId, (int) $data['index'], $request->file('chunk'), $request->user());

        return response()->json($this->uploads->status($uploadId, $request->user()));
    }

    public function status(Request $request, string $uploadId): JsonResponse
    {
        Gate::authorize('upload', Media::class);

        return response()->json($this->uploads->status($uploadId, $request->user()));
    }

    public function complete(Request $request, string $uploadId): JsonResponse
    {
        Gate::authorize('upload', Media::class);

        $data = $request->validate([
            'model_type' => ['required', 'string'],
            'model_id' => ['required', 'integer'],
        ]);

        $model = $this->resolveModel($data['model_type'], (int) $data['model_id']);
        $item = $this->uploads->complete($uploadId, $model, $request->user());

        return response()->json([
            'id' => $item->id,
            'uuid' => $item->uuid,
            'name' => $item->displayName(),
            'collection' => $item->collection_name,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'disk' => $item->disk,
            'url' => $item->isPrivate() ? null : $item->publicUrl('medium'),
            'download_url' => match ($item->mediaType()) {
                \App\Enums\MediaType::Video => null,
                \App\Enums\MediaType::Document => app(\App\Services\Media\MediaService::class)
                    ->temporaryUrl($item),
                default => $item->publicUrl(),
            },
        ], 201);
    }

    protected function enforceRateLimit(Request $request, bool $chunk = false): void
    {
        $max = $chunk
            ? (int) setting('media.chunk_max_attempts', config('media.rate_limit.chunk_max_attempts', 1200))
            : (int) setting('media.upload_max_attempts', config('media.rate_limit.max_attempts', 30));
        $decay = (int) config('media.rate_limit.decay_minutes', 1) * 60;
        $key = ($chunk ? 'media-chunk:' : 'media-upload:').$request->user()->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'chunk' => 'Too many upload requests. Please wait a moment and try again.',
            ]);
        }

        RateLimiter::hit($key, $decay);
    }

    protected function resolveModel(string $alias, int $id): Model
    {
        $class = config("media.upload_models.{$alias}");

        if (! is_string($class) || ! class_exists($class)) {
            throw ValidationException::withMessages(['model_type' => 'Unknown media owner.']);
        }

        $model = $class::query()->find($id);

        if (! $model instanceof Model) {
            throw ValidationException::withMessages(['model_id' => 'The media owner could not be found.']);
        }

        return $model;
    }
}
