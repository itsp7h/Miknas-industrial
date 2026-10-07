<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'item_id', 'quantity', 'rate', 'total_amount', 'quantity_received',
        'system_unit', 'supplier_unit', 'unit_factor', 'supplier_quantity', 'supplier_rate', 'supplier_quantity_received'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_factor' => 'float',
        'supplier_quantity' => 'float',
        'supplier_rate' => 'float',
        'supplier_quantity_received' => 'float',
    ];

    /**
     * Whether this line is ordered in the supplier's unit (see SupplierUnit).
     * It need not say what that holds in ours: the GRN decides that.
     */
    public function inSupplierUnit(): bool
    {
        return filled($this->supplier_unit);
    }

    /** Ordered in the supplier's unit, with no factor yet. */
    public function conversionPending(): bool
    {
        return $this->inSupplierUnit() && ! ($this->unit_factor > 0);
    }

    /**
     * Everything ordered has arrived. A line in the supplier's unit is counted
     * in theirs, since without a factor there is no figure in ours to reach.
     */
    public function fullyReceived(): bool
    {
        return $this->inSupplierUnit()
            ? $this->supplier_quantity_received >= (float) $this->supplier_quantity
            : $this->quantity_received >= $this->quantity;
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
