<?php

namespace App\Filament\Resources\MediaResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class MediaUploadWidget extends Widget
{
    protected static string $view = 'filament.resources.media-upload-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('media.upload') ?? false;
    }
}
