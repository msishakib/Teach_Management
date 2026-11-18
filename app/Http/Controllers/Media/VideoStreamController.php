<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Media;
use App\Policies\LessonPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams uploaded lesson videos. Access is granted either by a valid signed
 * URL (which is only issued after an access check) or the media.view_private
 * permission. The lesson policy is re-checked on every request, so free
 * previews can be streamed by guests while enrolled lessons stay protected.
 */
class VideoStreamController extends Controller
{
    public function __invoke(
        Request $request,
        Media $media,
        LessonPolicy $policy,
    ): BinaryFileResponse|\Illuminate\Http\RedirectResponse {
        $user = $request->user();

        abort_unless($media->category() === MediaCategory::CourseVideo, 404);

        $media->loadMissing('model');

        $lesson = $media->model instanceof Lesson ? $media->model : null;

        if ($lesson !== null) {
            abort_unless($policy->view($user, $lesson), 403);
        }

        $authorised = $request->hasValidSignature() || ($user?->can('media.view_private') ?? false);

        abort_unless($authorised, 403);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        if (config("filesystems.disks.{$media->disk}.driver") !== 'local') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes((int) config('media.signed_url_ttl', 5))));
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $media->mime_type ?: 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
