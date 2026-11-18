<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\NoticeResource;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notices = Notice::query()
            ->published()
            ->visibleToPortalUser($user)
            ->when($request->filled('audience'), fn ($query) => $query->where('audience', $request->string('audience')))
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->paginate(25);

        return NoticeResource::collection($notices);
    }
}
