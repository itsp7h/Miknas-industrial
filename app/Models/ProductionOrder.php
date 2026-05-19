<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = ['order_number', 'product_id', 'quantity_to_produce', 'quantity_produced', 'production_date', 'completion_date', 'status', 'notes', 'created_by'];

    protected $casts = [
        'production_date' => 'date',
        'completion_date' => 'date',
        'quantity_to_produce' => 'decimal:2',
        'quantity_produced' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function materialIssues()
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function outputs()
    {
        return $this->hasMany(ProductionOutput::class);
    }

    public function cost()
    {
        return $this->hasOne(ProductionCost::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
