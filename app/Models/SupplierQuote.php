<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_invitation_id', 'purchase_request_id', 'supplier_id', 'reference',
        'submitted_at', 'lead_time_days', 'payment_terms', 'notes',
        'total_amount', 'document_path', 'document_name', 'document_size',
    ];

    /** Where a supplier's own quotation document is kept: private, like a GRN's paperwork. */
    public const DISK = 'local';

    /**
     * The supplier's own quotation, as attached in the portal, or null. Opened
     * from a web route because a new tab carries the session but not what
     * Sanctum needs for /api.
     *
     * @return array{name: string, size: int|null, url: string}|null
     */
    public function documentInfo(): ?array
    {
        if (! $this->document_path) {
            return null;
        }

        return [
            'name' => $this->document_name,
            'size' => $this->document_size,
            'url' => route('purchase.quotes.document', $this->id, false),
        ];
    }

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

    /**
     * The quote's total from its lines, as the portal adds it up on submit:
     * each line to three decimals, then VAT per vatable line.
     */
    public function recalculateTotal(float $vatRate): void
    {
        $total = $this->items()->get()->reject->not_available->sum(function ($line) use ($vatRate) {
            $vat = $line->is_vatable && $vatRate > 0 ? round($line->total_price * $vatRate / 100, 3) : 0;

            return $line->total_price + $vat;
        });

        $this->update(['total_amount' => round($total, 3)]);
    }
}
