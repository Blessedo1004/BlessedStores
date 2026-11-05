<?php

use Livewire\Component;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
    #[Layout('components.page-layout')]
    #[Title('Payment Successful')]

    public Order $order;
    public Payment $payment;
    public bool $reconciliationRequired = false;

    public function mount(Request $request): void
    {
        $reference = $request->query('reference');
        abort_unless(is_string($reference) && $reference !== '', 404);

        $this->payment = Payment::query()
            ->with(['order.storeOrders.orderItems'])
            ->where('paystack_reference', $reference)
            ->where('status', Payment::STATUS_SUCCESS)
            ->firstOrFail();
        $this->order = $this->payment->order;
        $this->reconciliationRequired = (bool) data_get($this->payment->provider_metadata, 'reconciliation_required', false);
    }
};
?>

<div>
    <section class="section-space">
        <div class="container payment-success-container">
            <div class="payment-success-card text-center">
                <div class="payment-success-icon" aria-hidden="true">✓</div>
                @if($reconciliationRequired)
                    <h1 class="h2 fw-bold mb-3">Payment received</h1>
                    <p class="text-muted mb-4">Your payment was successful. We are checking stock availability and will contact you shortly about your order.</p>
                @else
                    <h1 class="h2 fw-bold mb-3">Payment successful</h1>
                    <p class="text-muted mb-4">Your order has been created and will be processed shortly. A confirmation email has been sent to {{ $order->customer_email }}.</p>
                @endif

                <div class="payment-success-order text-start">
                    <div class="payment-success-detail-row">
                        <span class="text-muted">Order number</span>
                        <strong>{{ $order->order_id }}</strong>
                    </div>
                    <div class="payment-success-detail-row">
                        <span class="text-muted">Payment reference</span>
                        <strong>{{ $payment->paystack_reference }}</strong>
                    </div>
                    <div class="payment-success-detail-row">
                        <span class="text-muted">Order status</span>
                        <strong>{{ ucfirst($order->status) }}</strong>
                    </div>
                    <hr>
                    @foreach($order->storeOrders as $storeOrder)
                        @foreach($storeOrder->orderItems as $item)
                            <div class="payment-success-item-row">
                                <span>{{ $item->product_name }}@if($item->variant_name) — {{ $item->variant_name }}@endif × {{ $item->quantity }}</span>
                                <span class="payment-success-amount">₦{{ number_format((float) $item->line_subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    @endforeach
                    <hr>
                    <div class="payment-success-total-row">
                        <span>Order total</span>
                        <span>₦{{ number_format((float) $order->total_payable, 2) }}</span>
                    </div>
                </div>

                <a href="{{ route('home') }}" class="fill-btn payment-success-home-button">
                    <span class="fill-btn-inner">
                        <span class="fill-btn-normal">Continue shopping</span>
                        <span class="fill-btn-hover">Continue shopping</span>
                    </span>
                </a>
            </div>
        </div>
    </section>
</div>
