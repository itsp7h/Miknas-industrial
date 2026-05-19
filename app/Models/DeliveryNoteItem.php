<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_note_id', 'sales_order_item_id', 'item_id', 'quantity_delivered'];

    protected $casts = ['quantity_delivered' => 'decimal:2'];

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
