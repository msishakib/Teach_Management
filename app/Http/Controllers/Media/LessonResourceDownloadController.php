<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Policies\LessonResourcePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves downloadable lesson attachments. The lesson-resource policy mirrors
 * lesson access, so enrolled learners and free-preview visitors can download
 * exactly the files they are allowed to see.
 */
class LessonResourceDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Lesson $lesson,
        LessonResource $resource,
        LessonResourcePolicy $policy,
    ): StreamedResponse|\Illuminate\Http\RedirectResponse {
        abort_unless((int) $resource->lesson_id === (int) $lesson->getKey(), 404);

        $resource->loadMissing(['fileMedia', 'lesson']);

        abort_unless($policy->download($request->user(), $resource), 403);

        $media = $resource->file();

        abort_unless($media !== null, 404);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

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
