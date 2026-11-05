<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketLot extends Model
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PAUSED   = 'paused';
    public const STATUS_SOLD_OUT = 'sold_out';

    protected $fillable = [
        'ticket_type_id',
        'name',
        'price',
        'quantity',
        'starts_at',
        'ends_at',
        'status',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'quantity'  => 'integer',
            'sold'      => 'integer',
            'reserved'  => 'integer',
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'sort'      => 'integer',
        ];
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Cupo disponible. null = sin límite propio (lo limitan las butacas) */
    public function available(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }

        return max(0, $this->quantity - $this->sold - $this->reserved);
    }

    public function hasStock(int $qty = 1): bool
    {
        $available = $this->available();

        return $available === null || $available >= $qty;
    }

    public function isOnSale(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return $this->hasStock();
    }
}
