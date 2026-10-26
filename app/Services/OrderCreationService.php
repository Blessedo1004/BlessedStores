<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderCreationService
{
    private const COMMISSION_RATE = 3;
    private const RESERVATION_MINUTES = 30;
    private const MINIMUM_ORDER_CENTS = 10000;

    public function __construct(private readonly CartService $cartService)
    {
    }

    /**
     * Create an unpaid order and reserve stock. No payment call is made here.
     * Cart rows are removed only after the database transaction commits.
     */
    public function create(array $checkout, ?int $userId = null): Order
    {
        $cartItems = $this->cartService->items()->values();
        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['payment' => 'Your cart is empty. Add an item before creating an order.']);
        }

        $order = DB::transaction(function () use ($checkout, $userId, $cartItems): Order {
            $productIds = $cartItems->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages(['payment' => 'A product in your cart is no longer available. Review your cart and try again.']);
            }

            $storeIds = $products->pluck('store_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $stores = Store::query()
                ->whereIn('id', $storeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($stores->count() !== $storeIds->count()) {
                throw ValidationException::withMessages(['payment' => 'A store in your cart is no longer available.']);
            }

            $variantIds = $cartItems->pluck('variant_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $variants = $variantIds->isEmpty()
                ? collect()
                : ProductVariant::query()->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages(['payment' => 'A product option in your cart is no longer available. Review your cart and try again.']);
            }

            $lines = $this->prepareLines($cartItems, $products, $stores, $variants);
            $groupedLines = $lines->groupBy('store_id');
            $storeTotals = $groupedLines->map(fn (Collection $storeLines) => $storeLines->sum('line_cents'));
            $subtotalCents = $storeTotals->sum();

            if ($subtotalCents < self::MINIMUM_ORDER_CENTS) {
                throw ValidationException::withMessages(['payment' => 'The minimum order total is ₦100.']);
            }

            $commissionCentsByStore = $storeTotals->map(fn (int $amount) => $this->commissionCents($amount));
            $commissionTotalCents = $commissionCentsByStore->sum();
            $shippingCents = 0;
            $taxCents = 0;

            $order = Order::create([
                'order_id' => $this->newPublicOrderId(),
                'user_id' => $userId,
                'customer_name' => $checkout['name'],
                'customer_email' => strtolower($checkout['email']),
                'customer_phone' => $checkout['phone_number'],
                'delivery_address' => $checkout['address'],
                'delivery_city' => $checkout['city'],
                'delivery_state' => $checkout['state'],
                'delivery_postal_code' => $checkout['postal_code'] ?? null,
                'product_subtotal' => $this->money($subtotalCents),
                'commission_total' => $this->money($commissionTotalCents),
                'shipping_amount' => $this->money($shippingCents),
                'tax_amount' => $this->money($taxCents),
                'total_payable' => $this->money($subtotalCents + $shippingCents + $taxCents),
                'currency' => 'NGN',
                'status' => Order::STATUS_UNPAID,
                'paid_at' => null,
            ]);

            foreach ($groupedLines as $storeId => $storeLines) {
                $store = $stores->get((int) $storeId);
                $storeSubtotalCents = (int) $storeTotals->get($storeId);
                $commissionCents = (int) $commissionCentsByStore->get($storeId);
                $storeOrder = StoreOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'product_subtotal' => $this->money($storeSubtotalCents),
                    'commission_rate' => self::COMMISSION_RATE,
                    'commission_amount' => $this->money($commissionCents),
                    'vendor_share' => $this->money($storeSubtotalCents - $commissionCents),
                ]);

                $storeLines = $this->allocateLineCommissions($storeLines, $commissionCents);
                foreach ($storeLines as $line) {

                    OrderItem::create([
                        'store_order_id' => $storeOrder->id,
                        'product_id' => $line['product']->id,
                        'product_variant_id' => $line['variant']?->id,
                        'product_name' => $line['product']->name,
                        'variant_name' => $line['variant']?->name,
                        'sku' => $line['variant']?->sku ?? $line['product']->sku,
                        'quantity' => $line['quantity'],
                        'unit_price' => $this->money($line['unit_cents']),
                        'line_subtotal' => $this->money($line['line_cents']),
                        'commission_rate' => self::COMMISSION_RATE,
                        'commission_amount' => $this->money($line['commission_cents']),
                    ]);

                    StockReservation::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'product_variant_id' => $line['variant']?->id,
                        'quantity' => $line['quantity'],
                        'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                        'status' => StockReservation::STATUS_RESERVED,
                    ]);
                }
            }

            return $order;
        }, 3);

        try {
            $this->cartService->removeItems($cartItems);
        } catch (Throwable $exception) {
            report($exception);
            // The durable order/reservations are already committed; do not pretend checkout failed.
        }

        return $order;
    }

    private function prepareLines(Collection $cartItems, Collection $products, Collection $stores, Collection $variants): Collection
    {
        $lines = collect();
        $requested = [];

        foreach ($cartItems as $item) {
            $product = $products->get((int) $item->product_id);
            $variantId = filled($item->variant_id ?? null) ? (int) $item->variant_id : null;
            $variant = $variantId ? $variants->get($variantId) : null;
            $store = $stores->get((int) $product->store_id);
            $quantity = (int) $item->quantity;

            if ($quantity < 1) {
                throw ValidationException::withMessages(['payment' => "The quantity for {$product->name} is invalid."]);
            }
            if ($product->visibility !== 'published') {
                throw ValidationException::withMessages(['payment' => "{$product->name} is no longer available for purchase."]);
            }
            if ($store->store_status !== 'active' || $store->payment_setup_status !== 'ready') {
                throw ValidationException::withMessages(['payment' => "{$store->name} cannot currently accept orders. Remove its products from your cart or try later."]);
            }
            if ($product->productVariants()->exists() && ! $variantId) {
                throw ValidationException::withMessages(['payment' => "Choose an available option for {$product->name}."]);
            }
            if ($variantId && (! $variant || (int) $variant->product_id !== (int) $product->id)) {
                throw ValidationException::withMessages(['payment' => "A selected option for {$product->name} is no longer available."]);
            }

            $unitPrice = $variant ? $variant->price : $product->price;
            $stock = $variant ? $variant->quantity : $product->quantity;
            if ($unitPrice === null || $stock === null || (int) $stock < 1) {
                throw ValidationException::withMessages(['payment' => "{$product->name} is out of stock."]);
            }

            $inventoryKey = $product->id . ':' . ($variant?->id ?? 'product');
            $requested[$inventoryKey] = ($requested[$inventoryKey] ?? 0) + $quantity;
            $unitCents = $this->cents($unitPrice);
            $lines->push([
                'product' => $product,
                'variant' => $variant,
                'store_id' => (int) $store->id,
                'quantity' => $quantity,
                'unit_cents' => $unitCents,
                'line_cents' => $unitCents * $quantity,
                'inventory_key' => $inventoryKey,
            ]);
        }

        $reservedByInventory = StockReservation::query()
            ->selectRaw('product_id, product_variant_id, SUM(quantity) as reserved_quantity')
            ->active()
            ->whereIn('product_id', $products->keys())
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id . ':' . ($row->product_variant_id ?? 'product'));

        foreach ($requested as $key => $quantity) {
            [$productId, $variantKey] = explode(':', $key, 2);
            $inventoryKey = $productId . ':' . $variantKey;
            $reservation = $reservedByInventory->get($inventoryKey);
            $reservedQuantity = (int) ($reservation->reserved_quantity ?? 0);
            $product = $products->get((int) $productId);
            $variant = $variantKey === 'product' ? null : $variants->get((int) $variantKey);
            $available = (int) ($variant ? $variant->quantity : $product->quantity) - $reservedQuantity;

            if ($quantity > $available) {
                throw ValidationException::withMessages(['payment' => "Only {$available} item(s) remain available for {$product->name}. Review your cart and try again."]);
            }
        }

        return $lines;
    }

    private function allocateLineCommissions(Collection $lines, int $storeCommissionCents): Collection
    {
        $allocated = $lines->map(fn (array $line) => min(
            $line['line_cents'],
            intdiv($line['line_cents'] * self::COMMISSION_RATE, 100)
        ));
        $remainder = $storeCommissionCents - $allocated->sum();

        foreach ($lines->keys() as $index) {
            if ($remainder <= 0) {
                break;
            }

            $capacity = $lines[$index]['line_cents'] - $allocated[$index];
            $increment = min($capacity, $remainder);
            $allocated[$index] += $increment;
            $remainder -= $increment;
        }

        return $lines->map(function (array $line, int $index) use ($allocated): array {
            $line['commission_cents'] = $allocated[$index];
            return $line;
        });
    }

    private function newPublicOrderId(): string
    {
        return 'BS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
    }

    private function cents(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function commissionCents(int $subtotalCents): int
    {
        return intdiv(($subtotalCents * self::COMMISSION_RATE) + 50, 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
