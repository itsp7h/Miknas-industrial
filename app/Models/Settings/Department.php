<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'settings_departments';

    protected $fillable = ['name', 'company_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
