<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact numbers for the people on System → Requested By — as many as a
 * person has (mobile, office, site), so a JSON list rather than a column each.
 *
 * Nullable and additive: the release before this one never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requesters', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('requesters', function (Blueprint $table) {
            $table->dropColumn('phones');
        });
    }
};
