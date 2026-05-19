<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseSignature extends Model
{
    protected $fillable = [
        'purchase_request_id', 'signed_by', 'signature_image', 'signed_at', 'ip_address',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
