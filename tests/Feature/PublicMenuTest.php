<?php

namespace Tests\Feature;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\RoleName;
use App\Filament\Resources\MenuItemResource\Pages\ManageMenuItems;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_menu_is_seeded_and_rendered_in_both_languages(): void
    {
        $this->assertSame(7, MenuItem::query()->where('location', 'header')->count());

        $this->get('/en/gallery')
            ->assertOk()
            ->assertSee('href="'.url('en').'#courses"', false)
            ->assertSee('Teachers')
            ->assertSee(__('site.nav.more', [], 'en'));

        $this->get('/bn')
            ->assertOk()
            ->assertSee('href="#courses"', false)
            ->assertSee('শিক্ষক');
    }

    public function test_admin_changes_show_up_on_the_site_immediately(): void
    {
        $this->get('/en')->assertOk();

        MenuItem::query()->where('target', 'teachers')->update(['is_active' => false]);
        MenuItem::query()->where('target', 'teachers')->first()->save();

        MenuItem::query()->create([
            'location' => MenuLocation::Header,
            'label' => 'Prospectus',
            'link_type' => MenuLinkType::Custom,
            'target' => 'https://example.com/prospectus.pdf',
            'open_in_new_tab' => true,
            'sort_order' => 1,
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertSee('href="https://example.com/prospectus.pdf"', false)
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertDontSee('href="#teachers"', false);
    }

    public function test_unsafe_custom_urls_are_never_rendered(): void
    {
        MenuItem::query()->create([
            'location' => MenuLocation::Footer,
            'label' => 'Evil',
            'link_type' => MenuLinkType::Custom,
            'target' => 'javascript:alert(1)',
            'sort_order' => 1,
        ]);

        $this->get('/en')->assertOk()->assertDontSee('javascript:alert(1)', false);
    }

    public function test_admin_form_rejects_unsafe_urls(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        Livewire::actingAs($admin)
            ->test(ManageMenuItems::class)
            ->callAction('create', [
                'location' => 'header',
                'link_type' => 'custom',
                'target' => 'javascript:alert(1)',
                'label' => 'Evil',
            ])
            ->assertHasActionErrors(['target']);

        Livewire::actingAs($admin)
            ->test(ManageMenuItems::class)
            ->callAction('create', [
                'location' => 'header',
                'link_type' => 'page',
                'target' => 'apply',
                'label' => 'Admission',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('menu_items', ['label' => 'Admission', 'target' => 'apply']);
    }
}
