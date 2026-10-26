<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id', 'paystack_reference', 'expected_amount', 'currency', 'status',
        'provider_transaction_id', 'paid_at', 'verified_at', 'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}