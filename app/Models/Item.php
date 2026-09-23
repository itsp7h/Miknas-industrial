<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['item_code', 'item_name', 'category', 'item_category_id', 'unit_of_measure', 'minimum_stock_level', 'cost_price', 'description', 'is_active'];

    protected $casts = [
        'minimum_stock_level' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function itemCategory()
    {
        return $this->belongsTo(ItemCategory::class);
    }

    /**
     * The full classification as a person reads it — "Raw Materials / Chemical
     * Materials", or just the type when the item is in no section.
     */
    public function categoryPath(): string
    {
        $type = ItemCategory::PARENT_LABELS[$this->category] ?? $this->category;

        return $this->itemCategory ? $type.' / '.$this->itemCategory->name : $type;
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * The stock levels with their warehouses, loaded once.
     *
     * The list eager-loads them; a save's response and its broadcast each
     * carry a single item and do not, and a stale empty there would blank the
     * quantity and warehouse the list is already showing. `load` caches onto
     * the model, so the two readers below share one query either way.
     */
    protected function loadedStockLevels()
    {
        if (! $this->relationLoaded('stockLevels')) {
            $this->load('stockLevels.warehouse');
        }

        return $this->stockLevels;
    }

    /** On-hand across every warehouse. */
    public function totalQuantity(): float
    {
        return (float) $this->loadedStockLevels()->sum('quantity');
    }

    /**
     * Where the item is stocked, biggest holding first.
     *
     * A level of 0 is kept. It used to be dropped as "a warehouse the item has
     * left", but a warehouse is now assigned on the item form before any stock
     * exists — every imported item starts at 0 — and dropping those made the
     * assignment invisible, which is the whole point of making it.
     */
    public function stockByWarehouse(): array
    {
        return $this->loadedStockLevels()
            ->filter(fn ($level) => (bool) $level->warehouse)
            ->map(fn ($level) => [
                'id' => $level->warehouse_id,
                'name' => $level->warehouse->name,
                'quantity' => (float) $level->quantity,
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Purchase orders that are a purchase: issued, and not called off. */
    public const PURCHASED_STATUSES = ['sent', 'partial', 'received'];

    /**
     * When this item was last bought, as Y-m-d, or null if it never was.
     *
     * A draft order is not yet a purchase and a cancelled one never became
     * one, so only issued orders count — the date is the LPO's, which is when
     * the company committed to buying, rather than when the goods turned up.
     *
     * The list pre-fills this for every row in one query (see
     * ItemController::index) because asking per item would be one query per
     * row; the fallback here is what answers for a single item, such as the
     * one echoed back after a save.
     */
    public function lastPurchasedAt(): ?string
    {
        if (array_key_exists('last_purchased_at', $this->attributes)) {
            return $this->attributes['last_purchased_at'];
        }

        $date = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_order_items.item_id', $this->id)
            ->whereIn('purchase_orders.status', self::PURCHASED_STATUSES)
            ->max('purchase_orders.po_date');

        return $date ? substr((string) $date, 0, 10) : null;
    }

    public function billOfMaterials()
    {
        return $this->hasMany(BillOfMaterial::class, 'product_id');
    }

    public function bomComponents()
    {
        return $this->hasMany(BillOfMaterial::class, 'raw_material_id');
    }

    /**
     * Every table that refuses to let go of an item, and what to call it.
     *
     * All but the last two are ON DELETE RESTRICT: deleting a referenced item
     * fails at the database, which surfaced as a 500 carrying a raw
     * QueryException rather than telling anyone what was in the way. The last
     * two cascade, so the database would allow the delete and quietly take the
     * history with it — worse than refusing.
     *
     * Keyed by table rather than by relation because the constraint lives in
     * the schema: a relation this model happens not to declare still stops the
     * delete, so the list has to mirror the migrations, not the model.
     */
    private const BLOCKING_REFERENCES = [
        ['purchase_order_items', 'item_id', 'purchase orders'],
        ['grn_items', 'item_id', 'goods receipts'],
        ['material_issues', 'item_id', 'material issues'],
        ['production_orders', 'product_id', 'production orders'],
        ['production_outputs', 'item_id', 'production outputs'],
        ['bill_of_materials', 'raw_material_id', 'bills of materials'],
        ['sales_order_items', 'item_id', 'sales orders'],
        ['delivery_note_items', 'item_id', 'delivery notes'],
        ['bill_of_materials', 'product_id', 'bills of materials'],
        ['stock_movements', 'item_id', 'stock movements'],
    ];

    /**
     * What is using this item, in the words the refusal message uses. Empty
     * means nothing points at it and it can safely be deleted.
     */
    public function blockingReferences(): array
    {
        $used = collect(self::BLOCKING_REFERENCES)
            ->filter(fn ($reference) => DB::table($reference[0])->where($reference[1], $this->id)->exists())
            ->pluck(2);

        // Stock still on a shelf is not a reference but is just as good a
        // reason: the level cascades away, and with it the record that the
        // warehouse is holding something.
        if ($this->stockLevels()->where('quantity', '!=', 0)->exists()) {
            $used->push('stock on hand');
        }

        return $used->unique()->values()->all();
    }
}
