<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A supplier quoting in their own unit no longer says what it holds in ours:
 * whoever receives the goods does, on the GRN, before it is confirmed. So:
 *
 * - a GRN line received in the supplier's unit keeps what arrived in theirs
 *   (supplier_quantity BAG at supplier_rate) and, once someone with
 *   `goods-receipts.convert-units` sets it, the factor that turns it into ours
 *   — who set it and when beside it;
 * - an LPO line counts what has arrived in the supplier's unit too, because
 *   with no factor on the order there is no figure in ours to count against;
 * - `unit_conversions` remembers the last factor used for an item in a unit,
 *   so the next GRN starts from it.
 *
 * Expand only: all nullable or defaulted, and the release before this one
 * runs on it unchanged (CLAUDE.md, "a migration must work with the release
 * before it").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->string('supplier_unit', 50)->nullable()->after('unit_cost');
            $table->decimal('supplier_quantity', 12, 3)->nullable()->after('supplier_unit');
            $table->decimal('supplier_rate', 12, 3)->nullable()->after('supplier_quantity');
            $table->decimal('unit_factor', 14, 4)->nullable()->after('supplier_rate');
            $table->foreignId('converted_by')->nullable()->after('unit_factor')->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_by');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('supplier_quantity_received', 12, 3)->default(0)->after('supplier_rate');
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 50);
            $table->decimal('factor', 14, 4);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'unit']);
        });

        // An order already in the supplier's unit, with its factor, has been
        // received in ours so far. Count that in theirs as well, or an order
        // half-received before this would never read as fully received.
        DB::table('purchase_order_items')
            ->whereNotNull('supplier_unit')
            ->where('unit_factor', '>', 0)
            ->where('quantity_received', '>', 0)
            ->update(['supplier_quantity_received' => DB::raw('ROUND(quantity_received / unit_factor, 3)')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('supplier_quantity_received');
        });

        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_by');
            $table->dropColumn(['supplier_unit', 'supplier_quantity', 'supplier_rate', 'unit_factor', 'converted_at']);
        });
    }
};
