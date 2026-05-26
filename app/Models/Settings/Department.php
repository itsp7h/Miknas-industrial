<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'settings_departments';

    protected $fillable = ['name', 'project_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProjectSetting::class, 'project_id');
    }
}
