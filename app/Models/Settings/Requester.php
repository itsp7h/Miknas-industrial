<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A person an MPR can be raised for — a name, not a user account — and the
 * companies they raise requests for. The request stores the name itself, so
 * renaming or deleting a requester never rewrites a request already raised.
 */
class Requester extends Model
{
    protected $fillable = ['name', 'phones'];

    /** Contact numbers, in the order they were entered. */
    protected $casts = ['phones' => 'array'];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_requester', 'requester_id', 'company_id');
    }
}
