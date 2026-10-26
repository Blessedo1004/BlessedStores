<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DELIVERED = 'delivered';

    protected $fillable = [
        'order_id', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
        'delivery_address', 'delivery_city', 'delivery_state', 'delivery_postal_code',
        'product_subtotal', 'commission_total', 'shipping_amount', 'tax_amount',
        'total_payable', 'currency', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'product_subtotal' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, StoreOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}