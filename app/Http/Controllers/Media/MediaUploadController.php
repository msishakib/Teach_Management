<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Direct (single request) upload endpoint for the central media module.
 */
class MediaUploadController extends Controller
{
    public function __invoke(Request $request, MediaService $media): JsonResponse
    {
        Gate::authorize('upload', Media::class);

        $this->enforceRateLimit($request);

        $data = $request->validate([
            'file' => ['required', 'file'],
            'category' => ['required', Rule::enum(MediaCategory::class)],
            'model_type' => ['required', 'string'],
            'model_id' => ['required', 'integer'],
        ]);

        $category = MediaCategory::from($data['category']);
        $model = $this->resolveModel($data['model_type'], (int) $data['model_id']);

        if (! in_array($category, $model->mediaCategories(), true)) {
            throw ValidationException::withMessages([
                'category' => 'This model does not accept the selected media category.',
            ]);
        }

        $item = $media->store($request->file('file'), $model, $category, $request->user());

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
                \App\Enums\MediaType::Document => $media->temporaryUrl($item),
                default => $item->publicUrl(),
            },
        ], 201);
    }

    protected function enforceRateLimit(Request $request): void
    {
        $max = (int) setting('media.upload_max_attempts', config('media.rate_limit.max_attempts', 30));
        $decay = (int) config('media.rate_limit.decay_minutes', 1) * 60;
        $key = 'media-upload:'.$request->user()->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'file' => 'Too many uploads. Please wait a moment and try again.',
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
