<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'venue_section_id',
        'name',
        'description',
        'max_per_order',
        'is_visible',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'max_per_order' => 'integer',
            'is_visible'    => 'boolean',
            'sort'          => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(VenueSection::class, 'venue_section_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(TicketLot::class)->orderBy('sort');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Es un tipo con butaca numerada */
    public function isSeated(): bool
    {
        return $this->section?->isSeated() ?? false;
    }

    /**
     * Lote vigente: el primero (por orden) que esté activo, dentro de fechas y con cupo.
     * Así funcionan las preventas: cuando se agota o vence uno, pasa al siguiente.
     */
    public function currentLot(): ?TicketLot
    {
        return $this->lots->first(fn (TicketLot $lot) => $lot->isOnSale());
    }
}
