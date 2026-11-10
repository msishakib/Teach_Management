<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->index('name', 'permissions_name_index');
            $table->index('guard_name', 'permissions_guard_name_index');
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->index('name', 'roles_name_index');
            $table->index('guard_name', 'roles_guard_name_index');
        });

        Schema::table($tableNames['role_has_permissions'], function (Blueprint $table) {
            $table->index('role_id', 'role_has_permissions_role_id_index');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropIndex('permissions_name_index');
            $table->dropIndex('permissions_guard_name_index');
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropIndex('roles_name_index');
            $table->dropIndex('roles_guard_name_index');
        });

        Schema::table($tableNames['role_has_permissions'], function (Blueprint $table) {
            $table->dropIndex('role_has_permissions_role_id_index');
        });
    }
};
