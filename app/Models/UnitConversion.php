<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The last factor used for an item received in a supplier's unit: "1 BAG of
 * cement = 50 KG". A GRN's converter starts from it, and saving a conversion
 * there updates it.
 */
class UnitConversion extends Model
{
    protected $fillable = ['item_id', 'unit', 'factor', 'updated_by'];

    protected $casts = ['factor' => 'float'];

    public static function factorFor(int $itemId, ?string $unit): ?float
    {
        if (blank($unit)) {
            return null;
        }

        return static::where('item_id', $itemId)->where('unit', $unit)->value('factor');
    }

    public static function remember(int $itemId, string $unit, float $factor): void
    {
        static::updateOrCreate(
            ['item_id' => $itemId, 'unit' => $unit],
            ['factor' => $factor, 'updated_by' => auth()->id()],
        );
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
