<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'settings_companies';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function projects(): HasMany
    {
        return $this->hasMany(ProjectSetting::class, 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id');
    }
}
