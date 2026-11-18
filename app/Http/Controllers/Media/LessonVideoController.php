<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Lms\LessonPlaybackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonVideoController extends Controller
{
    public function __invoke(
        Request $request,
        Lesson $lesson,
        LessonPlaybackService $playback,
    ): JsonResponse {
        $payload = $playback->payload($request->user(), $lesson);

        abort_if($payload === null, 404, 'This lesson has no video.');

        return response()->json($payload);
    }
}
