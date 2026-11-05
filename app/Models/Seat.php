<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    public const KIND_STANDARD   = 'standard';
    public const KIND_ACCESSIBLE = 'accessible';
    public const KIND_COMPANION  = 'companion';

    protected $fillable = [
        'venue_section_id',
        'row_label',
        'number',
        'label',
        'x',
        'y',
        'kind',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'x'         => 'float',
            'y'         => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(VenueSection::class, 'venue_section_id');
    }

    public function eventSeats(): HasMany
    {
        return $this->hasMany(EventSeat::class);
    }

    /** Texto para mostrar: "Fila C - Butaca 12" */
    public function displayName(): string
    {
        return $this->label ?: "Fila {$this->row_label} - Butaca {$this->number}";
    }
}
