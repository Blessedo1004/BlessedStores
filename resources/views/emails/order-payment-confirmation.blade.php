<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment confirmed</title>
  @include('emails.partials.styles')
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header"><strong style="color:#765b3d;font-size:18px">BlessedStores</strong></div>
      <div class="email-content">
        <span class="success">Payment confirmed</span>
        <h1>Thank you, {{ $order->customer_name }}.</h1>
        <p>Your payment was received and your order has been created. Our team will process it shortly.</p>

        <div class="order">
          <strong>Order {{ $order->order_id }}</strong>
          <table class="items" role="presentation">
            @foreach($order->storeOrders as $storeOrder)
              @foreach($storeOrder->orderItems as $item)
                <tr>
                  <td>{{ $item->product_name }}@if($item->variant_name) — {{ $item->variant_name }}@endif × {{ $item->quantity }}</td>
                  <td>₦{{ number_format((float) $item->line_subtotal, 2) }}</td>
                </tr>
              @endforeach
            @endforeach
            <tr><td class="total">Order total</td><td class="total">₦{{ number_format((float) $order->total_payable, 2) }}</td></tr>
          </table>
          <p style="margin:14px 0 0;font-size:13px">Payment reference: {{ $payment->paystack_reference }}</p>
        </div>

        <p>If you need help with this order, reply to this email and include your order number.</p>
        <p>Thank you for shopping with BlessedStores.</p>
      </div>
      <div class="email-footer">© {{ date('Y') }} BlessedStores</div>
    </div>
  </div>
</body>
</html>
