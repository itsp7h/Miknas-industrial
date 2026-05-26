<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'settings_companies';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectSetting::class, 'company_id');
    }
}
