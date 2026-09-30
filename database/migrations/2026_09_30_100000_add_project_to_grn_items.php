<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project a consumable line is received for. Inventory lines go into a
 * warehouse and have none, and lines from before this have none either, so
 * the column is nullable and the release before this one runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('type')
                ->constrained('settings_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
