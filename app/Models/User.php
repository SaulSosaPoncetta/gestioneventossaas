<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'dni',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    /* ---------------- Relaciones ---------------- */

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'holder_user_id');
    }

    /* ---------------- Helpers de roles ---------------- */

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /** Rol del usuario en una productora (owner, admin, staff, door) o null */
    public function roleIn(Organizer|int $organizer): ?string
    {
        $id = $organizer instanceof Organizer ? $organizer->id : $organizer;

        return $this->organizers->firstWhere('id', $id)?->pivot?->role;
    }

    public function belongsToOrganizer(Organizer|int $organizer): bool
    {
        return $this->roleIn($organizer) !== null;
    }

    /** Puede administrar eventos, entradas y ventas de la productora */
    public function canManageOrganizer(Organizer|int $organizer): bool
    {
        return $this->isPlatformAdmin()
            || in_array($this->roleIn($organizer), [Organizer::ROLE_OWNER, Organizer::ROLE_ADMIN], true);
    }

    /** Puede operar el escáner de puerta de la productora */
    public function canScanFor(Organizer|int $organizer): bool
    {
        return $this->isPlatformAdmin() || $this->belongsToOrganizer($organizer);
    }
}
