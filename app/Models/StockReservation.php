<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StockReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'quantity', 'expires_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_RESERVED)
            ->where('expires_at', '>', now());
    }
}