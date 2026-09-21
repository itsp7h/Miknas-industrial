<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A section within an item type — "Raw Materials / Chemical Materials".
 *
 * The name is the second half only; `parent_type` supplies the first, so
 * renaming a section touches one row.
 */
class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_type', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    /** How items.category spells each type, and how a person does. */
    public const PARENT_LABELS = [
        'raw_material' => 'Raw Materials',
        'wip' => 'Work In Progress',
        'finished_good' => 'Finished Goods',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function parentLabel(): string
    {
        return self::PARENT_LABELS[$this->parent_type] ?? $this->parent_type;
    }

    /** What the dropdown, the table cell and the filter all show. */
    public function path(): string
    {
        return $this->parentLabel().' / '.$this->name;
    }

    /** Sections in the order the warehouse sheet lists them. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('parent_type')->orderBy('sort_order')->orderBy('name');
    }
}
