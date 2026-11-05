<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    public const RESULT_OK           = 'ok';
    public const RESULT_ALREADY_USED = 'already_used';
    public const RESULT_INVALID      = 'invalid';
    public const RESULT_WRONG_EVENT  = 'wrong_event';
    public const RESULT_CANCELLED    = 'cancelled';

    public const MESSAGES = [
        self::RESULT_OK           => 'Ingreso registrado',
        self::RESULT_ALREADY_USED => 'La entrada ya fue usada',
        self::RESULT_INVALID      => 'Código inválido',
        self::RESULT_WRONG_EVENT  => 'La entrada es de otro evento',
        self::RESULT_CANCELLED    => 'La entrada está anulada',
    ];

    protected $fillable = [
        'client_uuid',
        'event_id',
        'ticket_id',
        'user_id',
        'scanned_code',
        'result',
        'gate',
        'device',
        'offline',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'offline'    => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isOk(): bool
    {
        return $this->result === self::RESULT_OK;
    }

    public function message(): string
    {
        return self::MESSAGES[$this->result] ?? $this->result;
    }
}
