<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierQuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_quote_id', 'purchase_request_item_id', 'description', 'supplier_description', 'unit', 'quantity',
        'supplier_unit', 'unit_factor', 'supplier_quantity', 'supplier_unit_price',
        'unit_price', 'total_price', 'is_vatable', 'not_available',
        'is_awarded', 'award_reason', 'awarded_at', 'awarded_by',
    ];

    protected $casts = [
        'is_vatable' => 'boolean',
        'not_available' => 'boolean',
        'is_awarded' => 'boolean',
        'awarded_at' => 'datetime',
        'unit_factor' => 'float',
        'supplier_quantity' => 'float',
        'supplier_unit_price' => 'float',
    ];

    /** Whether the supplier quoted this line in a unit other than ours. */
    public function inSupplierUnit(): bool
    {
        return filled($this->supplier_unit);
    }

    /**
     * Quoted in their unit with no factor: what one holds in ours is settled
     * on the GRN, when the goods are in hand.
     */
    public function conversionPending(): bool
    {
        return $this->inSupplierUnit() && ! ($this->unit_factor > 0);
    }

    public function quote()
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }

    public function purchaseRequestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
