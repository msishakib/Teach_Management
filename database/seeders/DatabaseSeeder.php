<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
            FeeTypeSeeder::class,
            ExamTypeSeeder::class,
            GradingScaleSeeder::class,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'type' => UserType::Admin,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([RoleName::SuperAdmin->value]);

        // Demo courses, teachers, toppers, notices etc. for the public website.
        if (! app()->isProduction()) {
            $this->call(WebsiteDemoSeeder::class);
        }
    }
}
