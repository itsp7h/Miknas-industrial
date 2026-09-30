<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'item_id', 'quantity', 'rate', 'total_amount', 'quantity_received',
        'system_unit', 'supplier_unit', 'unit_factor', 'supplier_quantity', 'supplier_rate'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_factor' => 'float',
        'supplier_quantity' => 'float',
        'supplier_rate' => 'float',
    ];

    /** Whether this line is ordered in the supplier's unit (see SupplierUnit). */
    public function inSupplierUnit(): bool
    {
        return filled($this->supplier_unit) && $this->unit_factor > 0;
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
