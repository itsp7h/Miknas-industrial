<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RfqInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'token', 'channel',
        'sent_at', 'opened_at', 'expires_at', 'status', 'item_ids',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'item_ids' => 'array',
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

    /**
     * The items this supplier was actually invited to quote on. An invitation
     * may cover the whole request (`item_ids` null) or a subset of it, and the
     * portal's read side and its submit side must agree on which — they read
     * this rather than each re-deriving it, which is what they used to do.
     */
    public function quotedItems(): Collection
    {
        $items = $this->purchaseRequest->items;

        return $this->item_ids
            ? $items->whereIn('id', $this->item_ids)->values()
            : $items->values();
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
