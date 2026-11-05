<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\StockReservation;
use App\Models\StoreOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Client\ConnectionException;
use App\Exceptions\PaystackApiException;
use Throwable;

class OrderPaymentInitializationService
{
    public function __construct(private readonly PaystackService $paystack)
    {
    }

    /** Initialize Paystack for an existing, authorized unpaid order. */
    public function initialize(Order $order): string
    {
        $attempt = DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== Order::STATUS_UNPAID || $lockedOrder->paid_at !== null) {
                throw ValidationException::withMessages(['payment' => 'This order is not available for payment.']);
            }

            if ($lockedOrder->payments()->where('status', Payment::STATUS_SUCCESS)->exists()) {
                throw ValidationException::withMessages(['payment' => 'A successful payment is already recorded for this order.']);
            }

            $existingPending = $lockedOrder->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->latest('id')
                ->first();

            if ($existingPending) {
                $authorizationUrl = data_get($existingPending->provider_metadata, 'authorization_url');
                if (is_string($authorizationUrl) && $this->isPaystackAuthorizationUrl($authorizationUrl)) {
                    return ['authorization_url' => $authorizationUrl];
                }

                throw ValidationException::withMessages(['payment' => 'A payment attempt is already being processed or has an uncertain result. Please contact support before retrying.']);
            }

            $snapshot = $this->validateOrderSnapshot($lockedOrder);
            $reference = 'BS-' . Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(12));
            $payment = Payment::create([
                'order_id' => $lockedOrder->id,
                'paystack_reference' => $reference,
                'expected_amount' => $lockedOrder->total_payable,
                'currency' => $lockedOrder->currency,
                'status' => Payment::STATUS_PENDING,
                'provider_metadata' => ['initialization_state' => 'starting'],
            ]);

            return [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'order_id' => $lockedOrder->order_id,
                'email' => $lockedOrder->customer_email,
                'amount_kobo' => $this->toMinorUnits($lockedOrder->total_payable),
                'currency' => $lockedOrder->currency,
                'allocations' => $snapshot['allocations'],
            ];
        }, 3);

        if (isset($attempt['authorization_url'])) {
            return $attempt['authorization_url'];
        }

        $payment = Payment::query()->findOrFail($attempt['payment_id']);

        try {
            foreach ($attempt['allocations'] as $allocation) {
                $subaccount = $this->paystack->fetchSubaccount($allocation['subaccount_code']);
                if (! $this->isTrue(data_get($subaccount, 'active')) || ! $this->isTrue(data_get($subaccount, 'is_verified'))) {
                    throw ValidationException::withMessages(['payment' => "Store {$allocation['store_name']} is not currently eligible to receive payments."]);
                }
            }
        } catch (ValidationException $exception) {
            $this->markFailed($payment, 'subaccount_ineligible');
            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($payment, 'subaccount_check_failed');
            throw ValidationException::withMessages(['payment' => 'We could not confirm a store payment account with Paystack. Please try again later.']);
        }

        try {
            $split = $this->paystack->createSplitGroup([
                'name' => 'BlessedStores ' . $attempt['order_id'] . ' ' . substr($attempt['reference'], -8),
                'type' => 'flat',
                'currency' => $attempt['currency'],
                'bearer_type' => 'account',
                'subaccounts' => array_map(fn (array $allocation) => [
                    'subaccount' => $allocation['subaccount_code'],
                    // Paystack flat split shares are expressed in the currency's minor unit.
                    'share' => $allocation['share_kobo'],
                ], $attempt['allocations']),
            ]);
            $splitCode = data_get($split, 'data.split_code');
            if (! is_string($splitCode) || $splitCode === '') {
                $this->markFailed($payment, 'split_code_missing');
                throw ValidationException::withMessages(['payment' => 'Paystack did not return a usable multi-store split. Please try again later.']);
            }

            $payment->provider_metadata = [
                'initialization_state' => 'split_created',
                'split_code' => $splitCode,
            ];
            $payment->save();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            $this->markFailed($payment, 'split_creation_connection_failed');
            throw ValidationException::withMessages(['payment' => 'Could not set up the store payment allocations with Paystack. Please try again.']);
        } catch (PaystackApiException $exception) {
            $this->markFailed($payment, 'split_creation_rejected', [
                'provider_message' => $exception->providerMessage,
            ]);
            throw ValidationException::withMessages(['payment' => 'Paystack rejected the store payment allocation: ' . $exception->providerMessage]);
        } catch (Throwable $exception) {
            $this->markFailed($payment, 'split_creation_failed');
            throw ValidationException::withMessages(['payment' => 'Could not prepare store payment allocations. Please try again.']);
        }

        try {
            $response = $this->paystack->initializeTransaction([
                'email' => $attempt['email'],
                // The dashboard setting charges Paystack's processing fee to the customer;
                // submit only the order's persisted payable amount, without an app estimate.
                'amount' => $attempt['amount_kobo'],
                'currency' => $attempt['currency'],
                'reference' => $attempt['reference'],
                'callback_url' => route('payment.callback'),
                'split_code' => $splitCode,
                'metadata' => [
                    'order_id' => $attempt['order_id'],
                    'payment_reference' => $attempt['reference'],
                ],
            ]);
        } catch (ConnectionException $exception) {
            // Paystack may have accepted the request before the connection failed.
            $this->setPaymentMetadata($payment, [
                'initialization_state' => 'outcome_unknown',
                'split_code' => $splitCode,
            ]);
            throw ValidationException::withMessages(['payment' => 'Paystack did not confirm the payment request. Please contact support before retrying.']);
        } catch (PaystackApiException $exception) {
            $this->markFailed($payment, 'transaction_initialization_rejected', [
                'split_code' => $splitCode,
                'provider_message' => $exception->providerMessage,
            ]);
            throw ValidationException::withMessages(['payment' => 'Paystack could not start this payment: ' . $exception->providerMessage]);
        } catch (Throwable $exception) {
            $this->setPaymentMetadata($payment, [
                'initialization_state' => 'outcome_unknown',
                'split_code' => $splitCode,
            ]);
            throw ValidationException::withMessages(['payment' => 'We could not confirm whether Paystack started this payment. Please contact support before retrying.']);
        }

        $authorizationUrl = data_get($response, 'data.authorization_url');
        $returnedReference = data_get($response, 'data.reference');
        if (! is_string($authorizationUrl) || ! $this->isPaystackAuthorizationUrl($authorizationUrl)
            || $returnedReference !== $attempt['reference']) {
            $this->setPaymentMetadata($payment, [
                'initialization_state' => 'outcome_unknown',
                'split_code' => $splitCode,
            ]);
            throw ValidationException::withMessages(['payment' => 'Paystack returned an incomplete or mismatched payment response. Please contact support before retrying.']);
        }

        $this->setPaymentMetadata($payment, [
            'initialization_state' => 'initialized',
            'split_code' => $splitCode,
            'authorization_url' => $authorizationUrl,
        ]);

        return $authorizationUrl;
    }

    /** Validate Stage 2 snapshots and return vendor allocations in Paystack minor units. */
    private function validateOrderSnapshot(Order $order): array
    {
        $storeOrders = StoreOrder::query()
            ->with(['store', 'orderItems.product', 'orderItems.productVariant'])
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($storeOrders->isEmpty()) {
            throw ValidationException::withMessages(['payment' => 'This order has no store items and cannot be paid.']);
        }

        $storeSubtotalTotal = 0;
        $commissionTotal = 0;
        $itemSubtotalTotal = 0;
        $expectedReservations = [];
        $allocations = [];

        foreach ($storeOrders as $storeOrder) {
            $store = $storeOrder->store;
            if (! $storeOrder->store_id || ! $store || $store->store_status !== 'active'
                || $store->payment_setup_status !== 'ready' || ! filled($store->paystack_subaccount_code)) {
                throw ValidationException::withMessages(['payment' => 'Every store in this order must be active and have a ready Paystack settlement account.']);
            }

            $storeSubtotal = $this->toMinorUnits($storeOrder->product_subtotal);
            $commission = $this->toMinorUnits($storeOrder->commission_amount);
            $vendorShare = $this->toMinorUnits($storeOrder->vendor_share);
            $rate = (float) $storeOrder->commission_rate;
            $expectedCommission = (int) round($storeSubtotal * ($rate / 100), 0, PHP_ROUND_HALF_UP);
            $linesSubtotal = 0;
            $linesCommission = 0;

            if ($storeOrder->orderItems->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'A store order has no purchasable items. Please contact support.']);
            }

            foreach ($storeOrder->orderItems as $item) {
                $product = $item->product;
                $variant = $item->productVariant;
                if (! $item->product_id || ! $product || (int) $product->store_id !== (int) $store->id || $item->quantity < 1) {
                    throw ValidationException::withMessages(['payment' => 'An order item is no longer valid for payment.']);
                }
                if ($item->product_variant_id !== null && (! $variant || (int) $variant->product_id !== (int) $product->id)) {
                    throw ValidationException::withMessages(['payment' => 'A product option in this order no longer matches its product.']);
                }

                $lineSubtotal = $this->toMinorUnits($item->line_subtotal);
                $unitPrice = $this->toMinorUnits($item->unit_price);
                $lineCommission = $this->toMinorUnits($item->commission_amount);
                if ($lineSubtotal !== $unitPrice * (int) $item->quantity || $lineCommission > $lineSubtotal) {
                    throw ValidationException::withMessages(['payment' => 'The saved order item totals do not reconcile. Please contact support.']);
                }

                $linesSubtotal += $lineSubtotal;
                $linesCommission += $lineCommission;
                $key = $item->product_id . ':' . ($item->product_variant_id ?? 'product');
                $expectedReservations[$key] = ($expectedReservations[$key] ?? 0) + (int) $item->quantity;
            }

            if ($linesSubtotal !== $storeSubtotal || $linesCommission !== $commission
                || $commission !== $expectedCommission || $vendorShare !== $storeSubtotal - $commission) {
                throw ValidationException::withMessages(['payment' => 'The saved store totals do not reconcile. Please contact support.']);
            }

            $storeSubtotalTotal += $storeSubtotal;
            $commissionTotal += $commission;
            $itemSubtotalTotal += $linesSubtotal;
            if ($vendorShare > 0) {
                $allocations[] = [
                    'store_name' => $storeOrder->store_name,
                    'subaccount_code' => $store->paystack_subaccount_code,
                    'share_kobo' => $vendorShare,
                ];
            }
        }

        $productSubtotal = $this->toMinorUnits($order->product_subtotal);
        $orderCommission = $this->toMinorUnits($order->commission_total);
        $expectedTotal = $productSubtotal + $this->toMinorUnits($order->shipping_amount) + $this->toMinorUnits($order->tax_amount);
        if ($storeSubtotalTotal !== $productSubtotal || $itemSubtotalTotal !== $productSubtotal
            || $commissionTotal !== $orderCommission || $expectedTotal !== $this->toMinorUnits($order->total_payable)
            || $this->toMinorUnits($order->total_payable) < 1 || $allocations === [] || $order->currency !== 'NGN') {
            throw ValidationException::withMessages(['payment' => 'The saved order amounts do not reconcile. Please contact support.']);
        }

        $actualReservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->active()
            ->selectRaw('product_id, product_variant_id, SUM(quantity) as reserved_quantity')
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id . ':' . ($row->product_variant_id ?? 'product') => (int) $row->reserved_quantity,
            ])
            ->all();

        ksort($expectedReservations);
        ksort($actualReservations);
        if ($expectedReservations !== $actualReservations) {
            throw ValidationException::withMessages(['payment' => 'The stock reservation for this order has expired or is no longer valid. Please contact support.']);
        }

        return ['allocations' => $allocations];
    }

    private function markFailed(Payment $payment, string $reason, array $extra = []): void
    {
        $payment->status = Payment::STATUS_FAILED;
        $payment->provider_metadata = array_merge(
            is_array($payment->provider_metadata) ? $payment->provider_metadata : [],
            ['initialization_state' => $reason],
            $extra,
        );
        $payment->save();
    }

    private function setPaymentMetadata(Payment $payment, array $metadata): void
    {
        $payment->provider_metadata = array_merge(
            is_array($payment->provider_metadata) ? $payment->provider_metadata : [],
            $metadata,
        );
        $payment->save();
    }

    private function isTrue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function isPaystackAuthorizationUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'paystack.com' || str_ends_with($host, '.paystack.com'));
    }

    private function toMinorUnits(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }
}
