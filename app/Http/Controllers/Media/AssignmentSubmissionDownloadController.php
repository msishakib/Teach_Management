<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Media;
use App\Policies\AssignmentSubmissionPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentSubmissionDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        AssignmentSubmission $submission,
        Media $media,
        AssignmentSubmissionPolicy $policy,
    ): StreamedResponse|\Illuminate\Http\RedirectResponse {
        abort_unless($policy->viewFile($request->user(), $submission, $media), 403);

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
