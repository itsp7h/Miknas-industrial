<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuoteItem extends Model
{
    protected $fillable = [
        'supplier_quote_id', 'description', 'unit', 'quantity', 'unit_price', 'total_price',
    ];

    public function quote()
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }
}
