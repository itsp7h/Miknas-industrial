<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number', 'date', 'project_name', 'department',
        'requested_by_name', 'required_date_text', 'location',
        'remarks', 'status', 'stage', 'verified_by_name',
        'requested_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'date'        => 'date',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function signature()
    {
        return $this->hasOne(PurchaseSignature::class);
    }

    public function rfqInvitations()
    {
        return $this->hasMany(RfqInvitation::class);
    }

    public function supplierQuotes()
    {
        return $this->hasMany(SupplierQuote::class);
    }

    /**
     * Winning line items across all quotes for this request. A request can have
     * items awarded to different suppliers — there is no single "awarded quote".
     */
    public function awardedQuoteItems()
    {
        return $this->hasManyThrough(
            SupplierQuoteItem::class,
            SupplierQuote::class,
            'purchase_request_id',
            'supplier_quote_id',
        )->where('supplier_quote_items.is_awarded', true);
    }

    /**
     * True once every request item that received at least one quote has a winner.
     * Items nobody quoted are excluded — they need manual sourcing, not an award.
     */
    public function isFullyAwarded(): bool
    {
        $awardedItemIds = $this->awardedQuoteItems()->pluck('purchase_request_item_id')->all();

        foreach ($this->items as $item) {
            $quoted = SupplierQuoteItem::whereHas('quote', fn ($q) => $q->where('purchase_request_id', $this->id))
                ->where('purchase_request_item_id', $item->id)
                ->where('not_available', false)
                ->exists();

            if ($quoted && !in_array($item->id, $awardedItemIds)) {
                return false;
            }
        }

        return true;
    }
}
