<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One stage a purchase request reached: when, and who moved it there. */
class PurchaseRequestStageEvent extends Model
{
    protected $fillable = ['purchase_request_id', 'stage', 'user_id', 'actor_name', 'reached_at'];

    protected $casts = [
        'reached_at' => 'datetime',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
