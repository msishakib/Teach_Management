<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Locks in the N+1 fixes on the heaviest Filament list pages: the query count
 * for one table page must not grow as more rows are added to that page.
 */
class FilamentQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);
    }

    public function test_student_list_query_count_does_not_grow_with_row_count(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Student::factory()->count(5)->create();

        // Warm up Filament's panel boot queries before measuring.
        $this->listQueryCount($admin);

        $fewRows = $this->listQueryCount($admin);

        Student::factory()->count(20)->create();

        $manyRows = $this->listQueryCount($admin);

        $this->assertLessThanOrEqual(
            $fewRows + 2,
            $manyRows,
            "Student list ran {$manyRows} queries for 25 rows but {$fewRows} for 5 rows; a relation is likely lazy loading per row.",
        );
    }

    private function listQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)
            ->test(ListStudents::class)
            ->assertSuccessful();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
