<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Mail\OrderPaymentConfirmationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentConfirmationService
{
    public function __construct(private readonly PaystackService $paystack)
    {
    }

    /** Verify the provider response and atomically record payment and inventory effects. */
    public function verify(string $reference, ?array $eventData = null): string
    {
        $payment = Payment::query()->where('paystack_reference', $reference)->first();
        if (! $payment) {
            Log::warning('Paystack payment reference is unknown.', ['reference' => $reference]);
            return 'unknown';
        }

        if ($payment->status === Payment::STATUS_SUCCESS) {
            Log::info('Paystack payment was already processed.', ['payment_id' => $payment->id]);
            $this->sendConfirmationEmail((int) $payment->id);
            return 'duplicate';
        }

        try {
            $response = $this->paystack->verifyTransaction($reference);
        } catch (Throwable $exception) {
            Log::error('Paystack transaction verification failed.', ['payment_id' => $payment->id, 'exception' => $exception::class]);
            throw $exception;
        }

        $data = data_get($response, 'data');
        if (! is_array($data) || data_get($data, 'reference') !== $reference) {
            Log::warning('Paystack verification response reference mismatch.', ['payment_id' => $payment->id]);
            return 'mismatch';
        }

        if (data_get($data, 'status') !== 'success') {
            return 'unconfirmed';
        }

        // With dashboard fee pass-through enabled, amount is the customer's gross
        // charge while requested_amount is the base amount initialized by us.
        $minorAmount = data_get($data, 'amount');
        $requestedAmount = data_get($data, 'requested_amount');
        $currency = data_get($data, 'currency');
        if (! is_numeric($minorAmount) || $currency !== $payment->currency
            || ! $this->matchesExpectedAmount($requestedAmount, $minorAmount, data_get($data, 'fees'), $payment->expected_amount)) {
            Log::warning('Paystack transaction amount or currency mismatch.', ['payment_id' => $payment->id]);
            return 'mismatch';
        }

        if ($eventData !== null && (data_get($eventData, 'reference') !== $reference
            || (string) data_get($eventData, 'currency') !== (string) $currency
            || ! is_numeric(data_get($eventData, 'amount'))
            || (int) data_get($eventData, 'amount') !== (int) $minorAmount
            || data_get($eventData, 'status') !== 'success')) {
            Log::warning('Paystack webhook payload does not match verified transaction.', ['payment_id' => $payment->id]);
            return 'mismatch';
        }

        $result = DB::transaction(function () use ($payment, $reference, $data): array {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return ['result' => 'duplicate', 'payment_id' => $lockedPayment->id];
            }
            if ($lockedPayment->paystack_reference !== $reference
                || $lockedPayment->currency !== data_get($data, 'currency')
                || ! $this->matchesExpectedAmount(
                    data_get($data, 'requested_amount'),
                    data_get($data, 'amount'),
                    data_get($data, 'fees'),
                    $lockedPayment->expected_amount,
                )) {
                Log::warning('Payment attempt changed before confirmation.', ['payment_id' => $lockedPayment->id]);
                return ['result' => 'mismatch', 'payment_id' => $lockedPayment->id];
            }
            if ($order->status !== Order::STATUS_UNPAID && $order->paid_at === null) {
                Log::error('Successful payment belongs to an order in an unexpected state.', ['payment_id' => $lockedPayment->id, 'order_id' => $order->id]);
                throw new \RuntimeException('Order is not eligible for payment confirmation.');
            }

            $inventoryFinalized = $order->paid_at !== null
                ? true
                : $this->finalizeStock($order);

            $lockedPayment->status = Payment::STATUS_SUCCESS;
            $lockedPayment->provider_transaction_id = (string) data_get($data, 'id');
            $lockedPayment->paid_at = now();
            $lockedPayment->verified_at = now();
            $metadata = is_array($lockedPayment->provider_metadata) ? $lockedPayment->provider_metadata : [];
            $metadata['verification'] = [
                'gateway_response' => data_get($data, 'gateway_response'),
                'channel' => data_get($data, 'channel'),
                'requested_amount' => data_get($data, 'requested_amount'),
                'customer_charged_amount' => data_get($data, 'amount'),
                'paystack_fee' => data_get($data, 'fees'),
                'verified_at' => now()->toIso8601String(),
            ];
            if (! $inventoryFinalized) {
                $metadata['reconciliation_required'] = true;
                $metadata['reconciliation_reason'] = 'expired_reservation_stock_unavailable';
            }
            $lockedPayment->provider_metadata = $metadata;
            $lockedPayment->save();

            $order->paid_at = now();
            if ($inventoryFinalized) {
                $order->status = Order::STATUS_PENDING;
            }
            $order->save();

            Log::info($inventoryFinalized ? 'Paystack payment confirmed.' : 'Late Paystack payment needs stock reconciliation.', [
                'payment_id' => $lockedPayment->id,
                'order_id' => $order->id,
            ]);

            return [
                'result' => $inventoryFinalized ? 'confirmed' : 'reconciliation',
                'payment_id' => $lockedPayment->id,
                'send_confirmation_email' => true,
            ];
        }, 3);

        if (! empty($result['send_confirmation_email']) || $result['result'] === 'duplicate') {
            $this->sendConfirmationEmail((int) $result['payment_id']);
        }

        if ($result['result'] === 'mismatch') {
            Log::warning('Payment confirmation attempt did not match the saved payment.', ['payment_id' => $result['payment_id']]);
        }

        return $result['result'];
    }

    /** Process transaction details from a Paystack-signed webhook without an API round trip. */
    public function confirmSignedWebhook(string $reference, array $eventData): string
    {
        $payment = Payment::query()->where('paystack_reference', $reference)->first();
        if (! $payment) {
            Log::warning('Paystack webhook references an unknown payment.', ['reference' => $reference]);
            return 'unknown';
        }

        if ($payment->status === Payment::STATUS_SUCCESS) {
            Log::info('Paystack webhook payment was already processed.', ['payment_id' => $payment->id]);
            $this->sendConfirmationEmail((int) $payment->id);
            return 'duplicate';
        }

        $amount = data_get($eventData, 'amount');
        $requestedAmount = data_get($eventData, 'requested_amount');
        $fees = data_get($eventData, 'fees');
        $currency = data_get($eventData, 'currency');
        if (data_get($eventData, 'status') !== 'success'
            || data_get($eventData, 'reference') !== $reference
            || ! is_numeric($amount)
            || $currency !== $payment->currency
            || ! $this->matchesExpectedAmount($requestedAmount, $amount, $fees, $payment->expected_amount)) {
            Log::warning('Paystack signed webhook did not match the saved payment attempt.', ['payment_id' => $payment->id]);
            return 'mismatch';
        }

        $result = DB::transaction(function () use ($payment, $reference, $eventData, $requestedAmount, $amount, $fees, $currency): array {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return ['result' => 'duplicate', 'payment_id' => $lockedPayment->id];
            }
            if ($lockedPayment->paystack_reference !== $reference
                || $lockedPayment->currency !== $currency
                || ! $this->matchesExpectedAmount($requestedAmount, $amount, $fees, $lockedPayment->expected_amount)) {
                return ['result' => 'mismatch', 'payment_id' => $lockedPayment->id];
            }
            if ($order->status !== Order::STATUS_UNPAID && $order->paid_at === null) {
                throw new \RuntimeException('Order is not eligible for payment confirmation.');
            }

            $inventoryFinalized = $order->paid_at !== null ? true : $this->finalizeStock($order);
            $lockedPayment->status = Payment::STATUS_SUCCESS;
            $lockedPayment->provider_transaction_id = (string) data_get($eventData, 'id');
            $lockedPayment->paid_at = now();
            $lockedPayment->verified_at = now();
            $metadata = is_array($lockedPayment->provider_metadata) ? $lockedPayment->provider_metadata : [];
            $metadata['verification'] = [
                'source' => 'signed_webhook',
                'requested_amount' => $requestedAmount,
                'customer_charged_amount' => $amount,
                'paystack_fee' => $fees,
                'channel' => data_get($eventData, 'channel'),
                'verified_at' => now()->toIso8601String(),
            ];
            if (! $inventoryFinalized) {
                $metadata['reconciliation_required'] = true;
                $metadata['reconciliation_reason'] = 'expired_reservation_stock_unavailable';
            }
            $lockedPayment->provider_metadata = $metadata;
            $lockedPayment->save();

            $order->paid_at = now();
            if ($inventoryFinalized) {
                $order->status = Order::STATUS_PENDING;
            }
            $order->save();

            Log::info($inventoryFinalized ? 'Paystack payment confirmed from signed webhook.' : 'Signed Paystack payment needs stock reconciliation.', [
                'payment_id' => $lockedPayment->id,
                'order_id' => $order->id,
            ]);

            return [
                'result' => $inventoryFinalized ? 'confirmed' : 'reconciliation',
                'payment_id' => $lockedPayment->id,
                'send_confirmation_email' => true,
            ];
        }, 3);

        if (! empty($result['send_confirmation_email']) || $result['result'] === 'duplicate') {
            $this->sendConfirmationEmail((int) $result['payment_id']);
        }

        return $result['result'];
    }

    private function sendConfirmationEmail(int $paymentId): void
    {
        try {
            $shouldSend = DB::transaction(function () use ($paymentId): bool {
                $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
                $metadata = is_array($payment->provider_metadata) ? $payment->provider_metadata : [];
                if (data_get($metadata, 'confirmation_email.sent_at') || data_get($metadata, 'confirmation_email.claimed_at')) {
                    return false;
                }

                $metadata['confirmation_email']['claimed_at'] = now()->toIso8601String();
                $payment->provider_metadata = $metadata;
                $payment->save();

                return true;
            }, 3);

            if (! $shouldSend) {
                return;
            }

            $payment = Payment::query()->with(['order.storeOrders.orderItems'])->findOrFail($paymentId);
            Mail::to($payment->order->customer_email)->send(new OrderPaymentConfirmationEmail($payment->order, $payment));

            $metadata = is_array($payment->provider_metadata) ? $payment->provider_metadata : [];
            $metadata['confirmation_email']['sent_at'] = now()->toIso8601String();
            unset($metadata['confirmation_email']['claimed_at']);
            $payment->provider_metadata = $metadata;
            $payment->save();
        } catch (Throwable $exception) {
            try {
                $payment = Payment::query()->find($paymentId);
                if ($payment) {
                    $metadata = is_array($payment->provider_metadata) ? $payment->provider_metadata : [];
                    unset($metadata['confirmation_email']['claimed_at']);
                    $payment->provider_metadata = $metadata;
                    $payment->save();
                }
            } catch (Throwable $cleanupException) {
                Log::error('Could not release confirmation email claim.', ['payment_id' => $paymentId]);
            }
            Log::error('Order confirmation email could not be sent.', ['payment_id' => $paymentId, 'exception' => $exception::class]);
        }
    }

    /** @return bool true when all order item stock was safely consumed */
    private function finalizeStock(Order $order): bool
    {
        $items = $order->orderItems()->orderBy('order_items.id')->get();
        if ($items->isEmpty()) {
            throw new \RuntimeException('Paid order has no order items.');
        }

        $expected = [];
        foreach ($items as $item) {
            $key = $item->product_id . ':' . ($item->product_variant_id ?? 'product');
            $expected[$key] = ($expected[$key] ?? 0) + (int) $item->quantity;
        }

        $reservations = StockReservation::query()->where('order_id', $order->id)
            ->orderBy('product_id')->orderBy('product_variant_id')->orderBy('id')->lockForUpdate()->get();
        $consumed = $reservations->where('status', StockReservation::STATUS_CONSUMED);
        if ($consumed->isNotEmpty()) {
            $consumedMap = $consumed->groupBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?? 'product'))
                ->map(fn ($group) => $group->sum('quantity'))->all();
            ksort($expected); ksort($consumedMap);
            if ($expected !== $consumedMap) {
                throw new \RuntimeException('Consumed reservations do not match the order items.');
            }
            return true;
        }

        // Expiration cleanup may already have changed expired reservations to
        // released. Include both states when reconciling the original order hold.
        $reservationMap = $reservations->whereIn('status', [StockReservation::STATUS_RESERVED, StockReservation::STATUS_RELEASED])
            ->groupBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?? 'product'))
            ->map(fn ($group) => $group->sum('quantity'))->all();
        ksort($expected); ksort($reservationMap);
        if ($expected !== $reservationMap) {
            throw new \RuntimeException('Order stock reservations do not match order items.');
        }

        $productIds = $items->pluck('product_id')->unique()->sort()->values();
        $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variantIds = $items->pluck('product_variant_id')->filter()->unique()->sort()->values();
        $variants = $variantIds->isEmpty() ? collect() : ProductVariant::query()
            ->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // Group by inventory to handle multiple lines for the same SKU once.
        foreach ($expected as $key => $quantity) {
            [$productId, $variantId] = explode(':', $key, 2);
            $product = $products->get((int) $productId);
            $variant = $variantId === 'product' ? null : $variants->get((int) $variantId);
            if (! $product || ($variantId !== 'product' && (! $variant || (int) $variant->product_id !== (int) $productId))) {
                Log::error('Stock finalization failed because inventory record is missing.', ['order_id' => $order->id, 'inventory_key' => $key]);
                throw new \RuntimeException('Inventory record is missing for paid order.');
            }
            $isReserved = $reservations->where('status', StockReservation::STATUS_RESERVED)
                ->where('product_id', (int) $productId)->where('product_variant_id', $variantId === 'product' ? null : (int) $variantId)
                ->every(fn ($reservation) => $reservation->expires_at->isFuture());
            $stock = (int) ($variant ? $variant->quantity : $product->quantity);
            if (! $isReserved) {
                $otherReserved = StockReservation::query()->active()->where('order_id', '!=', $order->id)
                    ->where('product_id', (int) $productId)
                    ->where('product_variant_id', $variantId === 'product' ? null : (int) $variantId)->sum('quantity');
                if ($stock - (int) $otherReserved < $quantity) {
                    $reservations->where('status', StockReservation::STATUS_RESERVED)->each(function ($reservation) {
                        $reservation->status = StockReservation::STATUS_RELEASED;
                        $reservation->save();
                    });
                    Log::warning('Late successful payment cannot be fulfilled from current stock.', ['order_id' => $order->id, 'inventory_key' => $key]);
                    return false;
                }
            }
        }

        foreach ($expected as $key => $quantity) {
            [$productId, $variantId] = explode(':', $key, 2);
            $inventory = $variantId === 'product' ? $products->get((int) $productId) : $variants->get((int) $variantId);
            $inventory->quantity = (int) $inventory->quantity - $quantity;
            $inventory->save();
        }
        $reservations->whereIn('status', [StockReservation::STATUS_RESERVED, StockReservation::STATUS_RELEASED])->each(function ($reservation) {
            $reservation->status = StockReservation::STATUS_CONSUMED;
            $reservation->save();
        });

        return true;
    }

    private function minorUnits(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function matchesExpectedAmount(mixed $requestedAmount, mixed $chargedAmount, mixed $fees, string|int|float|null $expectedAmount): bool
    {
        $expectedMinor = $this->minorUnits($expectedAmount);
        if (is_numeric($requestedAmount)) {
            if ((int) $requestedAmount !== $expectedMinor || ! is_numeric($chargedAmount)) {
                return false;
            }

            return is_numeric($fees)
                ? (int) $chargedAmount === (int) $requestedAmount + (int) $fees
                : (int) $chargedAmount >= (int) $requestedAmount;
        }

        // Older/provider responses may omit requested_amount when no fee pass-
        // through was applied. In that case require the charged amount to match.
        return is_numeric($chargedAmount) && (int) $chargedAmount === $expectedMinor;
    }
}
