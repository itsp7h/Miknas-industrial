<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $table = 'settings_locations';

    protected $fillable = ['name', 'project_id', 'is_active', 'address', 'latitude', 'longitude'];

    protected $casts = ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectSetting::class, 'project_id');
    }
}
