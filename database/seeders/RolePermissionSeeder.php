<?php

namespace Database\Seeders;

use App\Services\PermissionCatalogService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = app(PermissionCatalogService::class);

        $catalog->syncPermissions();
        $catalog->syncRolesAndMatrix();
    }
}
