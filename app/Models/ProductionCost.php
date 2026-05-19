<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCost extends Model
{
    use HasFactory;

    protected $fillable = ['production_order_id', 'raw_material_cost', 'labor_cost', 'machine_cost', 'overhead_cost', 'total_cost', 'notes'];

    protected $casts = [
        'raw_material_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'machine_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
