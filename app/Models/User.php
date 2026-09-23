<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function routeNotificationFor(string $channel, mixed $notification = null): mixed
    {
        if ($channel === 'database') {
            return $this->notifications();
        }

        return $this->whatsapp_number;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Users holding a profile, as a query.
     *
     * `User::role()` throws when the role does not exist, so renaming a profile
     * took goods receipt, low-stock alerts and production down with a 500
     * rather than simply notifying nobody. A missing profile now means an empty
     * audience, which is what it actually is.
     */
    public static function withProfile(?string $profile)
    {
        if (! $profile || ! Role::where('name', $profile)->exists()) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::role($profile);
    }
}
