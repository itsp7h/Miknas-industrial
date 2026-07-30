<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchaseAccessSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('purchase_access');

        foreach (array_keys($config['permissions']) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach ($config['profiles'] as $profile => $permissions) {
            $role = Role::firstOrCreate(['name' => $profile]);
            $role->syncPermissions($permissions);
        }
    }
}
