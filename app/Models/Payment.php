<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'mp_payment_id',
        'status',
        'status_detail',
        'amount',
        'net_amount',
        'fee_amount',
        'marketplace_fee',
        'payment_method',
        'payer_email',
        'raw',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'net_amount'      => 'decimal:2',
            'fee_amount'      => 'decimal:2',
            'marketplace_fee' => 'decimal:2',
            'raw'             => 'array',
            'approved_at'     => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
