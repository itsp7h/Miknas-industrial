<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin', 'Accounts', 'Store Manager', 'Production Manager', 'Sales Manager'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@erp.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );
        $admin->assignRole('Admin');

        $warehouses = [
            ['code' => 'WH-MAIN', 'name' => 'Main Warehouse', 'location' => 'Main Building'],
            ['code' => 'WH-PROD', 'name' => 'Production Warehouse', 'location' => 'Factory Floor'],
            ['code' => 'WH-FG',   'name' => 'Finished Goods Warehouse', 'location' => 'Dispatch Area'],
        ];
        foreach ($warehouses as $wh) {
            Warehouse::firstOrCreate(['code' => $wh['code']], $wh);
        }

        $this->call(UrgencyLevelSeeder::class);
    }
}
