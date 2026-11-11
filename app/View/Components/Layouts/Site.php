<?php

namespace App\View\Components\Layouts;

use App\Services\SiteProfileService;
use App\Support\LocalizedUrl;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Site extends Component
{
    /** @var array<string, mixed> */
    public array $site;

    public string $canonical;

    /** @var array<string, string> locale => URL */
    public array $alternates;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
        public bool $noindex = false,
        public ?string $type = 'website',
    ) {
        $this->site = app(SiteProfileService::class)->profile();
        $this->canonical = LocalizedUrl::canonical();
        $this->alternates = LocalizedUrl::alternates();
    }

    public function render(): View
    {
        return view('components.layouts.site');
    }
}
