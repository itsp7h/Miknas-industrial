<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutput extends Model
{
    use HasFactory;

    protected $fillable = ['production_order_id', 'item_id', 'warehouse_id', 'quantity', 'output_date', 'notes', 'recorded_by'];

    protected $casts = [
        'output_date' => 'date',
        'quantity' => 'decimal:2',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
