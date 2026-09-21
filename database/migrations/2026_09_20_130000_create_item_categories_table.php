<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The second level of the item classification.
 *
 * `items.category` is the type — is this bought or is it made — and has only
 * ever had three values. What the warehouse actually works in is the section:
 * the Forkoll inventory sheet has grouped items under the same headings, in the
 * same order, in every monthly tab for a year. Those headings are this table.
 *
 * It is a table rather than a string on the item so that the two levels can be
 * asked about separately: "every raw material" must not depend on matching the
 * front of a label, and renaming a section must not mean rewriting every item
 * that carries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // The items.category value this section sits under, so the pair
            // renders as "Raw Materials / Chemical Materials".
            $table->string('parent_type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            // Nullable: an item need not be in a section, and finished goods
            // are not sectioned at all.
            $table->foreignId('item_category_id')->nullable()->after('category')
                ->constrained('item_categories')
                // A section in use must not vanish out from under its items;
                // the controller offers to move them instead.
                ->restrictOnDelete();
        });

        // The five sections the Forkoll sheet has always used, in its order.
        $now = now();
        DB::table('item_categories')->insert(collect([
            'Chemical Materials',
            'Natural Pigments (Colors)',
            'Bulk',
            'Forkoll Bags',
            'Others',
        ])->map(fn ($name, $i) => [
            'name' => $name,
            'parent_type' => 'raw_material',
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_category_id');
        });

        Schema::dropIfExists('item_categories');
    }
};
