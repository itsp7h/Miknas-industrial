<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuoteItem extends Model
{
    protected $fillable = [
        'supplier_quote_id', 'purchase_request_item_id', 'description', 'supplier_description', 'unit', 'quantity',
        'unit_price', 'total_price', 'is_vatable', 'not_available',
        'is_awarded', 'award_reason', 'awarded_at', 'awarded_by',
    ];

    protected $casts = [
        'is_vatable'    => 'boolean',
        'not_available' => 'boolean',
        'is_awarded'    => 'boolean',
        'awarded_at'    => 'datetime',
    ];

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
