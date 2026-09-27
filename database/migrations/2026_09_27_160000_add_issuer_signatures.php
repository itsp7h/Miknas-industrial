<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An LPO is signed by whoever issues it.
 *
 * `users.signature_image` is the person's own signature, drawn or uploaded
 * once on their profile and reused. `purchase_orders.prepared_signature` is the
 * copy taken the moment an LPO is issued: a document already sent to a
 * supplier must not change because its issuer later replaced their signature.
 * Both hold a PNG/JPEG data URL, as purchase_signatures already does, which
 * DomPDF renders without fetching anything.
 *
 * Nullable and additive: the release before this one never reads either.
 * LPOs issued before this carry no signature and stay that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->longText('signature_image')->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->longText('prepared_signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('prepared_signature');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_image');
        });
    }
};
