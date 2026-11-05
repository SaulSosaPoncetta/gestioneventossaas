<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueSection extends Model
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_SEATED  = 'seated';

    protected $fillable = [
        'venue_id',
        'name',
        'type',
        'capacity',
        'color',
        'layout',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'layout'   => 'array',
            'sort'     => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class)->orderBy('row_label')->orderBy('number');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function isSeated(): bool
    {
        return $this->type === self::TYPE_SEATED;
    }
}
