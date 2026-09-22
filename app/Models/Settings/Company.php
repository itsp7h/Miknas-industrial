<?php

namespace App\Models\Settings;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'settings_companies';

    protected $fillable = ['name', 'lpo_code', 'warehouse_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Where this company's purchases are received. Null means no yard is implied. */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ProjectSetting::class, 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id');
    }
}
