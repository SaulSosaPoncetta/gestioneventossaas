<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    protected $fillable = [
        'event_id',
        'organizer_id',
        'user_id',
        'coupon_id',
        'buyer_name',
        'buyer_email',
        'buyer_dni',
        'buyer_phone',
        'status',
        'quantity',
        'subtotal',
        'discount',
        'service_fee',
        'total',
        'platform_fee',
        'currency',
        'expires_at',
        'paid_at',
        'mp_preference_id',
        'mp_payment_id',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'subtotal'     => 'decimal:2',
            'discount'     => 'decimal:2',
            'service_fee'  => 'decimal:2',
            'total'        => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'expires_at'   => 'datetime',
            'paid_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    /** En las URLs públicas se usa el uuid, nunca el id */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /* ---------------- Relaciones ---------------- */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function heldSeats(): HasMany
    {
        return $this->hasMany(EventSeat::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /** Pendientes cuya reserva ya venció (para liberar cupo) */
    public function scopeExpiredPending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /* ---------------- Helpers ---------------- */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function reservationExpired(): bool
    {
        return $this->isPending() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Lo que recibe la productora (total menos lo que retiene la plataforma) */
    public function organizerAmount(): float
    {
        return round((float) $this->total - (float) $this->platform_fee, 2);
    }
}
