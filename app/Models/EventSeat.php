<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventSeat extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_HELD      = 'held';
    public const STATUS_SOLD      = 'sold';
    public const STATUS_BLOCKED   = 'blocked';

    protected $fillable = [
        'event_id',
        'seat_id',
        'ticket_type_id',
        'status',
        'order_id',
        'held_until',
    ];

    protected function casts(): array
    {
        return [
            'held_until' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /** Disponible para comprar (libre, o retenida con la reserva vencida) */
    public function isSelectable(): bool
    {
        if ($this->status === self::STATUS_AVAILABLE) {
            return true;
        }

        return $this->status === self::STATUS_HELD
            && $this->held_until !== null
            && $this->held_until->isPast();
    }
}
