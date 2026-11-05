<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'request_id',
        'topic',
        'external_id',
        'payload',
        'attempts',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'attempts'     => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    public function markProcessed(): void
    {
        $this->forceFill(['processed_at' => now(), 'error' => null])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'error'    => mb_substr($error, 0, 2000),
        ])->save();
    }
}
