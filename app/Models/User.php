<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLES = [
        'system_admin' => 'System Administrator',
        'tl_canvasser' => 'TL Canvasser',
        'canvasser' => 'Canvasser',
    ];

    public const PERMISSIONS = [
        'view-data' => ['system_admin', 'tl_canvasser', 'canvasser'],
        'edit-data' => ['system_admin', 'tl_canvasser'],
        'delete-data' => ['system_admin'],
        'manage-users' => ['system_admin'],
    ];

    public function canAccessSystem(): bool
    {
        return $this->exists && ! $this->trashed() && $this->is_active
            && array_key_exists($this->role, self::ROLES);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes, Notifiable;

    public function isOnline(): bool
    {
        return $this->canAccessSystem() && $this->last_seen_at?->gt(now()->subMinutes(2));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
