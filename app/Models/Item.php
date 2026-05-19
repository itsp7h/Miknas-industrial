<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['item_code', 'item_name', 'category', 'unit_of_measure', 'minimum_stock_level', 'cost_price', 'description', 'is_active'];

    protected $casts = [
        'minimum_stock_level' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function billOfMaterials()
    {
        return $this->hasMany(BillOfMaterial::class, 'product_id');
    }

    public function bomComponents()
    {
        return $this->hasMany(BillOfMaterial::class, 'raw_material_id');
    }
}
