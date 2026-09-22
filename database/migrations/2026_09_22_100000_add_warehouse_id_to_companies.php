<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The warehouse a company's purchases land in.
 *
 * A request names its company, so the goods it brings in have a yard already
 * implied: Miknas Industrial receives at Askar, Steel Tech at Hidd. Until now
 * the goods receipt form offered every warehouse on every receipt, which made
 * booking Miknas stock into Hidd a slip of the mouse rather than a decision.
 *
 * Nullable, and `on delete set null`: a company with no warehouse set behaves
 * exactly as before — the receipt offers the full list — and deleting a
 * warehouse unlinks the companies pointing at it rather than taking them with
 * it.
 */
return new class extends Migration
{
    /** The mapping as the business states it, by the words in the names. */
    private const SEED = [
        'Miknas Industrial' => 'askar',
        'Steel Tech' => 'hidd',
    ];

    public function up(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('lpo_code')
                ->constrained('warehouses')->nullOnDelete();
        });

        foreach (self::SEED as $company => $warehouse) {
            $warehouseId = DB::table('warehouses')
                ->where('code', 'like', "%{$warehouse}%")
                ->orWhere('name', 'like', "%{$warehouse}%")
                ->value('id');

            // Absent on a fresh database — tests and CI seed neither of these
            // warehouses, and an unlinked company is a valid state.
            if ($warehouseId) {
                DB::table('settings_companies')->where('name', $company)
                    ->update(['warehouse_id' => $warehouseId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
