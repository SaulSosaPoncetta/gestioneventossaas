<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organizer_id',
        'name',
        'address',
        'city',
        'province',
        'latitude',
        'longitude',
        'capacity',
        'has_seat_map',
    ];

    protected function casts(): array
    {
        return [
            'latitude'     => 'decimal:7',
            'longitude'    => 'decimal:7',
            'capacity'     => 'integer',
            'has_seat_map' => 'boolean',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(VenueSection::class)->orderBy('sort');
    }

    public function seats(): HasManyThrough
    {
        return $this->hasManyThrough(Seat::class, VenueSection::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->province])
            ->filter()
            ->implode(', ');
    }
}
