<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const STATUS_VALID     = 'valid';
    public const STATUS_USED      = 'used';
    public const STATUS_CANCELLED = 'cancelled';

    // Sin 0/O/1/I/L para que se pueda dictar o tipear en puerta sin errores
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH   = 10;

    protected $fillable = [
        'code',
        'order_id',
        'order_item_id',
        'event_id',
        'ticket_type_id',
        'event_seat_id',
        'holder_user_id',
        'attendee_name',
        'attendee_dni',
        'attendee_email',
        'status',
        'checked_in_at',
        'checked_in_by',
        'gate',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'version'       => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->code)) {
                $ticket->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = '';
            $max = strlen(self::CODE_ALPHABET) - 1;
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /* ---------------- Relaciones ---------------- */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function eventSeat(): BelongsTo
    {
        return $this->belongsTo(EventSeat::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_user_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TicketTransfer::class)->latest();
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /** En eventos nominativos: falta cargar nombre y DNI */
    public function needsAttendeeData(): bool
    {
        return $this->event->is_nominative
            && (empty($this->attendee_name) || empty($this->attendee_dni));
    }

    public function attendeeLabel(): string
    {
        if ($this->attendee_name) {
            return $this->attendee_dni
                ? "{$this->attendee_name} (DNI {$this->attendee_dni})"
                : $this->attendee_name;
        }

        return $this->order->buyer_name;
    }

    public function seatLabel(): ?string
    {
        return $this->eventSeat?->seat?->displayName();
    }
}
