<?php

namespace App\Models;

use App\Models\Settings\ProjectSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = ['goods_receipt_note_id', 'purchase_order_item_id', 'item_id', 'quantity_received', 'unit_cost', 'type', 'project_id'];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

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
