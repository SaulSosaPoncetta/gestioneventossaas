<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organizer extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_DOOR  = 'door';

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'cuit',
        'email',
        'phone',
        'logo_path',
        'description',
        'commission_percent',
        'status',
        'mp_user_id',
        'mp_access_token',
        'mp_refresh_token',
        'mp_public_key',
        'mp_token_expires_at',
        'mp_connected_at',
    ];

    protected $hidden = [
        'mp_access_token',
        'mp_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent'  => 'decimal:2',
            'mp_access_token'     => 'encrypted',
            'mp_refresh_token'    => 'encrypted',
            'mp_token_expires_at' => 'datetime',
            'mp_connected_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organizer $organizer) {
            if (empty($organizer->slug)) {
                $organizer->slug = static::uniqueSlug($organizer->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'productora';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /* ---------------- Relaciones ---------------- */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isMpConnected(): bool
    {
        return ! empty($this->mp_user_id) && ! empty($this->mp_access_token);
    }

    public function mpTokenExpired(): bool
    {
        return $this->mp_token_expires_at !== null && $this->mp_token_expires_at->isPast();
    }

    /** Puede vender entradas: activa y con Mercado Pago vinculado */
    public function canSell(): bool
    {
        return $this->isActive() && $this->isMpConnected();
    }

    /** Comisión efectiva (la propia o la general de la plataforma) */
    public function effectiveCommission(): float
    {
        return $this->commission_percent !== null
            ? (float) $this->commission_percent
            : (float) config('geventos.comision_porcentaje');
    }
}
