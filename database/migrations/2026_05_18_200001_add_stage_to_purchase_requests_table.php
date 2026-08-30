<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->enum('stage', [
                'draft', 'gm_approval', 'rfq', 'quoting',
                'comparison', 'lpo', 'receiving', 'payment', 'complete',
            ])->default('draft')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
