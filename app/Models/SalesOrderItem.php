<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['sales_order_id', 'item_id', 'quantity', 'price', 'total_amount', 'quantity_delivered'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity_delivered' => 'decimal:2',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
