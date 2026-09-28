<?php

namespace App\Models\Settings;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'settings_companies';

    protected $fillable = ['name', 'lpo_code', 'mpr_code', 'warehouse_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Tens of kilobytes each: only the Companies page (and documents) carry them. */
    protected $hidden = ['logo_image', 'stamp_image'];

    /** What the image routes accept, and the column each one fills. */
    public const IMAGES = ['logo' => 'logo_image', 'stamp' => 'stamp_image'];

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

    /** The people an MPR for this company can be raised for. */
    public function requesters(): BelongsToMany
    {
        return $this->belongsToMany(Requester::class, 'company_requester', 'company_id', 'requester_id');
    }
}
