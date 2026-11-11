<?php

namespace App\Models\Concerns;

use App\Services\WebpImageService;

/**
 * Converts freshly uploaded JPEG/PNG files on the public disk to WebP before save.
 *
 * Models declare the image columns via a `$webpColumns` property.
 */
trait ConvertsImagesToWebp
{
    protected static function bootConvertsImagesToWebp(): void
    {
        static::saving(function ($model): void {
            $columns = property_exists($model, 'webpColumns') ? $model->webpColumns : [];

            foreach ($columns as $column) {
                if (! $model->isDirty($column) || ! is_string($model->{$column}) || $model->{$column} === '') {
                    continue;
                }

                $model->{$column} = app(WebpImageService::class)->convert($model->{$column});
            }
        });
    }
}
