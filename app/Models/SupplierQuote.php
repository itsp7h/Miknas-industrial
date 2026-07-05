<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuote extends Model
{
    protected $fillable = [
        'rfq_invitation_id', 'purchase_request_id', 'supplier_id',
        'submitted_at', 'lead_time_days', 'payment_terms', 'notes',
        'total_amount',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function rfqInvitation()
    {
        return $this->belongsTo(RfqInvitation::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }

    /**
     * Line items on this quote that won an award. Awarding now happens
     * per item (a supplier can win some items on a request and lose others),
     * so there is no single quote-level "awarded" flag anymore.
     */
    public function awardedItems()
    {
        return $this->items->where('is_awarded', true);
    }

    public function hasAwardedItems(): bool
    {
        return $this->awardedItems()->isNotEmpty();
    }
}
