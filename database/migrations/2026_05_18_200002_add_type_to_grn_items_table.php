<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->enum('type', ['inventory', 'consumable'])->default('inventory')->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
