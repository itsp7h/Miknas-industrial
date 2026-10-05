<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A raw material a production run consumed, at the cost price of the day. */
class ProductionRunItem extends Model
{
    protected $fillable = ['production_run_id', 'item_id', 'warehouse_id', 'quantity', 'unit_cost', 'line_cost'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:3',
        'line_cost' => 'decimal:3',
    ];

    public function run()
    {
        return $this->belongsTo(ProductionRun::class, 'production_run_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
