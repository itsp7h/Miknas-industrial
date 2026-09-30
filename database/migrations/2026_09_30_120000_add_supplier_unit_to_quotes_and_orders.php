<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A supplier may quote in their own unit — BAG where the MPR asked for PCS —
 * provided they say what it holds ("1 BAG = 25 PCS"). The quote line keeps
 * what they offered (supplier_quantity BAG at supplier_unit_price each) beside
 * what it comes to in our unit (quantity, unit_price), which is what compares,
 * orders and stocks. The LPO keeps its own copy, as it does the Ref.
 *
 * All nullable: a line quoted in our own unit has none, as has every line from
 * before this, and the release before this one runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->string('supplier_unit', 50)->nullable()->after('unit');
            $table->decimal('unit_factor', 14, 4)->nullable()->after('supplier_unit');
            $table->decimal('supplier_quantity', 12, 3)->nullable()->after('unit_factor');
            $table->decimal('supplier_unit_price', 12, 3)->nullable()->after('supplier_quantity');
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('system_unit', 50)->nullable()->after('total_amount');
            $table->string('supplier_unit', 50)->nullable()->after('system_unit');
            $table->decimal('unit_factor', 14, 4)->nullable()->after('supplier_unit');
            $table->decimal('supplier_quantity', 12, 3)->nullable()->after('unit_factor');
            $table->decimal('supplier_rate', 12, 3)->nullable()->after('supplier_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_unit', 'unit_factor', 'supplier_quantity', 'supplier_unit_price']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['system_unit', 'supplier_unit', 'unit_factor', 'supplier_quantity', 'supplier_rate']);
        });
    }
};
