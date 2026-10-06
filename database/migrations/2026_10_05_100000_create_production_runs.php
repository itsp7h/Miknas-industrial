<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory → Production: one run makes a quantity of a finished good out of
 * raw materials, at cost.
 *
 * A run is posted, never edited — it moved stock both ways when it was saved,
 * and each line freezes the cost price it was charged at, so a later price
 * change does not rewrite what a past run cost.
 *
 * Money is kept to three decimals, as BHD needs (a fils is a thousandth).
 *
 * Two new tables and nothing else, so the release before this one runs on it
 * untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number')->unique();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->date('production_date');
            $table->decimal('total_cost', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained('production_runs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 3)->default(0);
            $table->decimal('line_cost', 14, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_run_items');
        Schema::dropIfExists('production_runs');
    }
};
