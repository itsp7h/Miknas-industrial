<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RfqInvitation extends Model
{
    use HasFactory;

    /** How long an invitation stays open. Quoted by the email and the WhatsApp text. */
    public const EXPIRY_DAYS = 14;

    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'token', 'channel',
        'sent_at', 'opened_at', 'expires_at', 'status', 'item_ids',
        'selected_by', 'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'item_ids' => 'array',
    ];

    /**
     * The invitation's own lifetime in whole days, for the messages that state
     * it in prose. Taken from the record rather than repeated as a literal, so
     * a changed expiry cannot leave the wording behind.
     */
    public function expiresInDays(): int
    {
        return (int) round($this->created_at->diffInDays($this->expires_at));
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** Who chose this supplier for the request. */
    public function selectedBy()
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    /** Who sent them the quote request. Not always the same person. */
    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
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
