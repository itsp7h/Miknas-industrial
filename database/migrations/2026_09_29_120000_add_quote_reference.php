<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supplier's own reference for their quotation ("Ref:"), which the
 * supplier gives on the quote portal and the LPO prints under the vendor's
 * name. The LPO keeps its own copy, taken when it is generated, as it keeps
 * the issuer's signature: a document that has gone out does not change.
 *
 * Nullable on both tables: quotes and LPOs from before this have none, and
 * the release before this one runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('supplier_id');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('quote_reference', 255)->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('quote_reference');
        });
    }
};
