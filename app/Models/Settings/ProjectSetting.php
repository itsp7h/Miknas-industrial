<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class ProjectSetting extends Model
{
    protected $table = 'settings_projects';

    protected $fillable = ['name', 'is_active', 'company_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function locations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Settings\Location::class, 'project_id');
    }

}
