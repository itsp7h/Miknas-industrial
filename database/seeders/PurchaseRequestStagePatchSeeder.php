<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseRequestStagePatchSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('purchase_requests')->where('status', 'approved')->whereNull('stage')->update(['stage' => 'rfq']);
        DB::table('purchase_requests')->where('status', 'rejected')->whereNull('stage')->update(['stage' => 'draft']);
        DB::table('purchase_requests')->where('status', 'ordered')->whereNull('stage')->update(['stage' => 'lpo']);
        DB::table('purchase_requests')->where('status', 'pending')->whereNull('stage')->update(['stage' => 'gm_approval']);
        DB::table('purchase_requests')->whereNull('stage')->update(['stage' => 'draft']);
    }
}
