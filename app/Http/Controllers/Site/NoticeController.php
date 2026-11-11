<?php

namespace App\Http\Controllers\Site;

use App\Enums\NoticeAudience;
use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NoticeController extends Controller
{
    public function index(): View
    {
        $notices = $this->publicNotices()
            ->latest('published_at')
            ->paginate(10);

        return view('site.notices.index', compact('notices'));
    }

    public function show(int $notice, ?string $slug = null): View|RedirectResponse
    {
        $notice = $this->publicNotices()->findOrFail($notice);

        if ($slug !== $notice->slug()) {
            return redirect()->route('notices.show', ['notice' => $notice->id, 'slug' => $notice->slug()], 301);
        }

        $related = $this->publicNotices()
            ->whereKeyNot($notice->id)
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'title', 'published_at']);

        return view('site.notices.show', compact('notice', 'related'));
    }

    /**
     * Only active, already-published notices addressed to everyone are public.
     */
    protected function publicNotices()
    {
        return Notice::query()
            ->published()
            ->where('audience', NoticeAudience::All->value);
    }
}
