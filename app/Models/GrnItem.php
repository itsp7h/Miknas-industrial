<?php

namespace App\Models;

use App\Models\Settings\ProjectSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = ['goods_receipt_note_id', 'purchase_order_item_id', 'item_id', 'quantity_received', 'unit_cost', 'type', 'project_id',
        'supplier_unit', 'supplier_quantity', 'supplier_rate', 'unit_factor', 'converted_by', 'converted_at'];

    // Three places, like every other quantity and price in the purchase
    // flow: a conversion (12.000 BD a BAG of 7) lands on a cost of 1.714.
    protected $casts = [
        'quantity_received' => 'decimal:3',
        'unit_cost' => 'decimal:3',
        'supplier_quantity' => 'float',
        'supplier_rate' => 'float',
        'unit_factor' => 'float',
        'converted_at' => 'datetime',
    ];

    /** Received in the supplier's unit (BAG), and so converted into ours before it stocks. */
    public function inSupplierUnit(): bool
    {
        return filled($this->supplier_unit);
    }

    /** In the supplier's unit, with no factor yet: Confirm waits for one. */
    public function conversionPending(): bool
    {
        return $this->inSupplierUnit() && ! ($this->unit_factor > 0);
    }

    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function goodsReceiptNote()
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** Where a consumable line is used; inventory lines have none. */
    public function project()
    {
        return $this->belongsTo(ProjectSetting::class, 'project_id');
    }
}
