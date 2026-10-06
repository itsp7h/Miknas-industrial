<?php

namespace App\Models;

use App\Models\Settings\Company;
use App\Models\Settings\ProjectSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;

    /**
     * A request's first stage is reached the moment it exists, whichever path
     * created it, so it is recorded here rather than in each controller.
     */
    protected static function booted(): void
    {
        static::created(function (PurchaseRequest $request) {
            $user = auth()->user();

            $request->stageEvents()->create([
                'stage' => $request->stage ?? 'draft',
                'user_id' => $user?->id,
                'actor_name' => $request->requested_by_name ?: $user?->name,
                'reached_at' => $request->created_at ?? now(),
            ]);
        });
    }

    protected $fillable = [
        'request_number', 'date', 'company_name', 'project_name', 'department',
        'requested_by_name', 'required_date_text', 'location',
        'remarks', 'status', 'stage', 'verified_by_name',
        'requested_by', 'approved_by', 'approved_at',
        'rejection_reason', 'rejected_by', 'rejected_at',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * The company this request belongs to, as a record.
     *
     * The LPO letterhead needs the company itself, not just its name. Requests
     * raised while the form had a single field were moved across by migration,
     * so `company_name` is the company on every row; the project fallback is
     * kept for a row written before that ran, where the value is still a
     * project name.
     */
    public function resolveCompany(): ?Company
    {
        if (! $this->company_name) {
            return null;
        }

        return Company::where('name', $this->company_name)->first()
            ?? ProjectSetting::where('name', $this->company_name)->with('company')->first()?->company;
    }

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

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** When each stage was reached and by whom, oldest first. */
    public function stageEvents()
    {
        return $this->hasMany(PurchaseRequestStageEvent::class)->orderBy('reached_at')->orderBy('id');
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

            if ($quoted && ! in_array($item->id, $awardedItemIds)) {
                return false;
            }
        }

        return true;
    }
}
