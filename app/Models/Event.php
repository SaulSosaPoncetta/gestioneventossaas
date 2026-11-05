<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FINISHED  = 'finished';

    protected $fillable = [
        'organizer_id',
        'venue_id',
        'title',
        'slug',
        'summary',
        'description',
        'cover_path',
        'category',
        'starts_at',
        'ends_at',
        'doors_open_at',
        'sales_start_at',
        'sales_end_at',
        'status',
        'is_seated',
        'is_nominative',
        'allow_transfers',
        'max_tickets_per_order',
        'service_fee_percent',
        'capacity',
        'published_at',
    ];

    protected $hidden = [
        'checkin_secret',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'             => 'datetime',
            'ends_at'               => 'datetime',
            'doors_open_at'         => 'datetime',
            'sales_start_at'        => 'datetime',
            'sales_end_at'          => 'datetime',
            'published_at'          => 'datetime',
            'is_seated'             => 'boolean',
            'is_nominative'         => 'boolean',
            'allow_transfers'       => 'boolean',
            'max_tickets_per_order' => 'integer',
            'service_fee_percent'   => 'decimal:2',
            'capacity'              => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = static::uniqueSlug($event->title);
            }
            if (empty($event->checkin_secret)) {
                $event->checkin_secret = bin2hex(random_bytes(32));
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'evento';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /* ---------------- Relaciones ---------------- */

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort');
    }

    public function lots(): HasManyThrough
    {
        return $this->hasManyThrough(TicketLot::class, TicketType::class);
    }

    public function eventSeats(): HasMany
    {
        return $this->hasMany(EventSeat::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }

    /* ---------------- Helpers ---------------- */

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** Está a la venta ahora mismo */
    public function isOnSale(): bool
    {
        if (! $this->isPublished() || $this->starts_at->isPast()) {
            return false;
        }
        if ($this->sales_start_at && $this->sales_start_at->isFuture()) {
            return false;
        }
        if ($this->sales_end_at && $this->sales_end_at->isPast()) {
            return false;
        }

        return true;
    }

    public function effectiveServiceFee(): float
    {
        return $this->service_fee_percent !== null
            ? (float) $this->service_fee_percent
            : (float) config('geventos.cargo_servicio_porcentaje');
    }

    public function maxPerOrder(): int
    {
        return min(
            $this->max_tickets_per_order ?: PHP_INT_MAX,
            (int) config('geventos.max_entradas_por_orden')
        );
    }

    /** Precio desde (el lote activo más barato) */
    public function priceFrom(): ?float
    {
        $min = $this->lots()->where('ticket_lots.status', TicketLot::STATUS_ACTIVE)->min('price');

        return $min !== null ? (float) $min : null;
    }
}
