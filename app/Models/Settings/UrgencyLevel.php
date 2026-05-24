<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class UrgencyLevel extends Model
{
    protected $table = 'settings_urgency_levels';

    protected $fillable = [
        'label', 'emoji', 'color_bg', 'color_text',
        'subtitle', 'sort_order', 'show_date_picker', 'is_active',
    ];

    protected $casts = [
        'show_date_picker' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
