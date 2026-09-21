<?php

namespace Database\Seeders;

use App\Support\AccessCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates every `<tab>.<action>` permission and the profiles that start a
 * person off with some of them.
 *
 * Permissions are only ever added here, never removed: a square an Admin has
 * granted to somebody must not disappear because a tab was renamed in config
 * without a migration for the grants.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AccessCatalog::permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (AccessCatalog::profiles() as $profile) {
            Role::firstOrCreate(['name' => $profile['name']])
                ->syncPermissions($profile['permissions']);
        }
    }
}
