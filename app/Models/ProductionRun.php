<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Inventory → Production run: a finished good made from raw materials, at cost. */
class ProductionRun extends Model
{
    protected $fillable = [
        'run_number', 'item_id', 'warehouse_id', 'quantity', 'production_date',
        'total_cost', 'unit_cost', 'notes', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'production_date' => 'date',
        'total_cost' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The raw materials it consumed. */
    public function lines()
    {
        return $this->hasMany(ProductionRunItem::class);
    }
}
