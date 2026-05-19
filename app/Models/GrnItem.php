<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = ['goods_receipt_note_id', 'purchase_order_item_id', 'item_id', 'quantity_received', 'unit_cost', 'type'];

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
}
