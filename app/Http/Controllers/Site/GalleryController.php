<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Contracts\View\View;

class GalleryController extends Controller
{
    public function __invoke(): View
    {
        $images = GalleryImage::query()->active()->ordered()->limit(60)->get();

        return view('site.gallery', compact('images'));
    }
}
