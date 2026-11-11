<?php

namespace App\Http\Controllers;

use App\Support\PermissionName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamMarksImportErrorController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('exam', 'update')),
            403
        );

        abort_unless($request->hasValidSignature(), 403);

        $disk = (string) $request->query('disk', 'local');
        $path = (string) $request->query('path');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, basename($path), [
            'Content-Type' => 'text/csv',
        ]);
    }
}
