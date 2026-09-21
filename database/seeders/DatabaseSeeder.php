<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The profiles in config/purchase_access.php are the roles. Admin,
        // Accounts, Store Manager, Production Manager and Sales Manager used to
        // be seeded here with no permissions at all and checked nowhere but
        // Admin, so they were labels rather than access.
        $this->call(AccessSeeder::class);

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
