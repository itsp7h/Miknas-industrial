<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillOfMaterial extends Model
{
    use HasFactory;

    protected $table = 'bill_of_materials';

    protected $fillable = ['product_id', 'raw_material_id', 'quantity_required', 'unit_of_measure', 'notes'];

    protected $casts = ['quantity_required' => 'decimal:2'];

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function rawMaterial()
    {
        return $this->belongsTo(Item::class, 'raw_material_id');
    }
}
