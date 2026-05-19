<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuote extends Model
{
    protected $fillable = [
        'rfq_invitation_id', 'purchase_request_id', 'supplier_id',
        'submitted_at', 'lead_time_days', 'payment_terms', 'notes',
        'total_amount', 'is_awarded', 'award_reason', 'awarded_at', 'awarded_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'awarded_at'   => 'datetime',
        'is_awarded'   => 'boolean',
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

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
