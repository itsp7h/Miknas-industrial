<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_number', 'sales_order_id', 'customer_id', 'warehouse_id', 'delivery_date', 'status', 'notes', 'dispatched_by'];

    protected $casts = ['delivery_date' => 'date'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'dispatched_by');
    }
}
