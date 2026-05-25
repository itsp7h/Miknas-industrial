<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'settings_locations';

    protected $fillable = ['name', 'project_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Settings\ProjectSetting::class, 'project_id');
    }
}
