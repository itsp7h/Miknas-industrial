<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'location', 'latitude', 'longitude', 'description', 'is_active'];

    // The coordinates are floats, not the strings a decimal cast returns: the
    // map takes numbers, and a JSON string there puts the pin nowhere.
    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function goodsReceiptNotes()
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    public function materialIssues()
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function productionOutputs()
    {
        return $this->hasMany(ProductionOutput::class);
    }

    /**
     * What points at this warehouse, as ['stock level' => 3, ...], empty when
     * nothing does.
     *
     * Every one of these is a foreign key: stock levels and movements cascade,
     * the rest restrict. Either way the warehouse cannot simply go — the first
     * would take the ledger with it, the second refuses at the database. So the
     * one list drives both the decision and the sentence the user reads.
     */
    public function dependentCounts(): array
    {
        return array_filter([
            'stock level' => $this->stockLevels()->count(),
            'stock movement' => $this->stockMovements()->count(),
            'goods receipt note' => $this->goodsReceiptNotes()->count(),
            'material issue' => $this->materialIssues()->count(),
            'delivery note' => $this->deliveryNotes()->count(),
            'production output' => $this->productionOutputs()->count(),
        ]);
    }
}
