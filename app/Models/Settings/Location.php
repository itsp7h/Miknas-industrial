<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'settings_locations';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
