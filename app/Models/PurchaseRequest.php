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

    public function awardedQuote()
    {
        return $this->hasOne(SupplierQuote::class)->where('is_awarded', true);
    }
}
