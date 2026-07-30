<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfqInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'token', 'channel',
        'sent_at', 'opened_at', 'expires_at', 'status', 'item_ids',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'expires_at' => 'datetime',
        'item_ids'   => 'array',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quote()
    {
        return $this->hasOne(SupplierQuote::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
