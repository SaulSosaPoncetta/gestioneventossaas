<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED   = 'fixed';

    protected $fillable = [
        'organizer_id',
        'event_id',
        'code',
        'type',
        'value',
        'max_uses',
        'min_tickets',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'          => 'decimal:2',
            'max_uses'       => 'integer',
            'used_count'     => 'integer',
            'reserved_count' => 'integer',
            'min_tickets'    => 'integer',
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
            'is_active'      => 'boolean',
        ];
    }

    /** El código siempre se guarda en mayúsculas y sin espacios */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function remainingUses(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count - $this->reserved_count);
    }

    /** Devuelve null si el cupón sirve, o el motivo por el que no sirve */
    public function rejectionReason(Event $event, int $quantity): ?string
    {
        if (! $this->is_active) {
            return 'El cupón no está activo.';
        }
        if ($this->organizer_id !== $event->organizer_id) {
            return 'El cupón no es válido para este evento.';
        }
        if ($this->event_id !== null && $this->event_id !== $event->id) {
            return 'El cupón no es válido para este evento.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'El cupón todavía no está vigente.';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'El cupón está vencido.';
        }
        if ($this->remainingUses() === 0) {
            return 'El cupón ya no tiene usos disponibles.';
        }
        if ($quantity < $this->min_tickets) {
            return "El cupón requiere al menos {$this->min_tickets} entradas.";
        }

        return null;
    }

    /** Descuento a aplicar sobre el subtotal (nunca mayor al subtotal) */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }
}
