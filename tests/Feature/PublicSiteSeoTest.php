<?php

namespace Tests\Feature;

use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_the_preferred_locale(): void
    {
        $this->get('/')->assertRedirect('/en');

        $this->withHeader('Accept-Language', 'bn-BD,bn;q=0.9')->get('/')->assertRedirect('/bn');

        $this->withCookie('locale', 'bn')
            ->withHeader('Accept-Language', 'en-US')
            ->get('/')
            ->assertRedirect('/bn');
    }

    public function test_each_locale_has_its_own_indexable_homepage_with_hreflang(): void
    {
        foreach (['en', 'bn'] as $locale) {
            $this->get("/{$locale}")
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'"', false)
                ->assertSee('<link rel="canonical" href="'.url($locale).'">', false)
                ->assertSee('<link rel="alternate" hreflang="en" href="'.url('en').'">', false)
                ->assertSee('<link rel="alternate" hreflang="bn" href="'.url('bn').'">', false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false)
                ->assertSee('"@type":"WebSite"', false);
        }

        $this->get('/fr')->assertNotFound();
    }

    public function test_legacy_unprefixed_urls_redirect_permanently(): void
    {
        $this->get('/gallery')->assertStatus(301)->assertRedirect('/en/gallery');
        $this->get('/notices?page=2')->assertStatus(301)->assertRedirect('/en/notices?page=2');
    }

    public function test_notice_urls_carry_a_slug_and_fix_wrong_ones(): void
    {
        $notice = Notice::query()->create([
            'title' => 'SSC পরীক্ষার রুটিন 2026',
            'body' => 'Routine attached.',
            'audience' => 'all',
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $canonical = route('notices.show', ['locale' => 'bn', 'notice' => $notice->id, 'slug' => 'ssc-পরীক্ষার-রুটিন-2026']);

        $this->get("/bn/notices/{$notice->id}")->assertStatus(301)->assertRedirect($canonical);
        $this->get("/bn/notices/{$notice->id}/old-title")->assertStatus(301)->assertRedirect($canonical);
        $this->get($canonical)->assertOk()->assertSee('SSC পরীক্ষার রুটিন 2026');
        $this->get("/notices/{$notice->id}")->assertStatus(301)->assertRedirect("/en/notices/{$notice->id}");
    }

    public function test_sitemap_lists_both_languages_with_alternates(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('en').'</loc>', false)
            ->assertSee('<loc>'.url('bn/gallery').'</loc>', false)
            ->assertSee('hreflang="x-default"', false);
    }
}
