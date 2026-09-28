<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project location's address in parts — road, block, city, country —
 * filled from the map pin (Nominatim's reverse lookup, in English) and
 * editable. `block` is Bahrain's: Nominatim files a block number as the
 * postcode.
 *
 * Additive and nullable only, so the release before this one runs on it
 * unchanged (CLAUDE.md, "a migration must work with the release before it").
 * The free-text `address` stays: it is what the address search reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_locations', function (Blueprint $table) {
            $table->string('road')->nullable()->after('address');
            $table->string('block', 20)->nullable()->after('road');
            $table->string('city')->nullable()->after('block');
            $table->string('country')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('settings_locations', function (Blueprint $table) {
            $table->dropColumn(['road', 'block', 'city', 'country']);
        });
    }
};
