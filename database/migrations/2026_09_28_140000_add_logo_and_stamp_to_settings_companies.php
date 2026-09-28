<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each company's logo and stamp, uploaded on Settings → Companies. Stored as
 * PNG/JPEG data URLs, as an issuer's signature is (users.signature_image):
 * DomPDF renders a data URL directly, and nothing has to live on a disk that
 * an atomic deploy swaps out.
 *
 * Additive and nullable, so the release before this one runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->longText('logo_image')->nullable()->after('name');
            $table->longText('stamp_image')->nullable()->after('logo_image');
        });
    }

    public function down(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->dropColumn(['logo_image', 'stamp_image']);
        });
    }
};
