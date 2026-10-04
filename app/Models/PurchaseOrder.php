<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = ['po_number', 'supplier_id', 'quote_reference', 'purchase_request_id', 'po_date', 'expected_delivery_date', 'total_amount', 'status', 'sent_at', 'sent_to', 'notes', 'created_by', 'prepared_signature', 'approved_by', 'approved_at', 'approved_signature'];

    /** Only the documents render it; it is tens of kilobytes. */
    protected $hidden = ['prepared_signature', 'approved_signature'];

    protected $casts = [
        'po_date' => 'date',
        'expected_delivery_date' => 'date',
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Issued and signed under Prepared By, but not yet under Approved By, so
     * it has not gone to the supplier. An LPO that was emailed before
     * approval existed is not waiting for anything; one whose send failed
     * back then waits like a new one, since nothing goes out unapproved now.
     */
    public function awaitingApproval(): bool
    {
        return is_null($this->approved_at)
            && is_null($this->sent_at)
            && in_array($this->status, ['draft', 'sent'], true);
    }

    /**
     * Why $user may not approve this LPO, or null when they may. The approver
     * must be someone other than the person who prepared it — two signatures
     * from one hand are one signature.
     */
    public function approvalBlockedFor(?User $user): ?string
    {
        if (! $this->awaitingApproval()) {
            return 'This LPO is not waiting for approval.';
        }
        if (! $user?->can('pipeline.approve-lpo')) {
            return 'You do not have permission to approve LPOs';
        }
        if ($this->created_by && (int) $this->created_by === (int) $user->id) {
            return 'You prepared this LPO, so someone else must approve it';
        }

        return null;
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceiptNotes()
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    public function supplierInvoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
