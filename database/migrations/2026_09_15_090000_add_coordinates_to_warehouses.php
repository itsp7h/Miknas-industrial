<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `location` stays the human-readable address the list searches on. These
     * two carry the point a user dropped on the map, so the pin can be put back
     * where they left it when the record is reopened.
     *
     * 7 decimal places is roughly a centimetre — far finer than a warehouse
     * needs, and the precision OpenStreetMap hands back.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
