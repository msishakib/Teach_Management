<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private media through a short-lived signed URL after a policy check.
 * The storage path is never exposed to the browser.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        Gate::authorize('view', $media);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        // Remote disks (S3/R2) hand off to a temporary URL.
        if (config("filesystems.disks.{$media->disk}.driver") !== 'local') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes((int) config('media.signed_url_ttl', 5))));
        }

        return $disk->download($path, $media->displayName(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
