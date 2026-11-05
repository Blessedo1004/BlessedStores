<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPaymentConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Payment $payment)
    {
        $this->order->loadMissing('storeOrders.orderItems');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment received — your BlessedStores order is being prepared');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-payment-confirmation');
    }
}
