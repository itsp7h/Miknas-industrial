<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialIssue extends Model
{
    use HasFactory;

    protected $fillable = ['issue_number', 'production_order_id', 'item_id', 'warehouse_id', 'quantity', 'issue_date', 'notes', 'issued_by'];

    protected $casts = [
        'issue_date' => 'date',
        'quantity' => 'decimal:2',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
