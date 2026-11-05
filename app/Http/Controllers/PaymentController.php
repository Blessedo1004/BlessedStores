<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use App\Services\PaymentConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly PaymentConfirmationService $confirmation,
    ) {
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        if (! is_string($reference) || $reference === '' || strlen($reference) > 100) {
            return redirect()->route('checkout')->with('error', 'We could not confirm this payment. Please contact support if you were charged.');
        }

        try {
            $result = $this->confirmation->verify($reference);
        } catch (Throwable $exception) {
            Log::error('Payment callback could not complete verification.', ['exception' => $exception::class]);
            return redirect()->route('checkout')->with('error', 'Payment confirmation is temporarily unavailable. If you were charged, please wait or contact support.');
        }

        if (in_array($result, ['confirmed', 'duplicate'], true)) {
            return redirect()->route('payment.successful', ['reference' => $reference]);
        }
        if ($result === 'reconciliation') {
            return redirect()->route('payment.successful', ['reference' => $reference]);
        }
        if ($result === 'unknown') {
            return redirect()->route('checkout')->with('error', 'We could not match this payment reference to an order. If you were charged, please contact support.');
        }

        return redirect()->route('checkout')->with('error', 'Payment is not confirmed. If you were charged, please contact support.');
    }

    public function webhook(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Paystack-Signature');
        $legacySignature = $request->header('x-paystack-signature');

        if (! $this->paystack->isValidWebhookSignature($rawBody, $signature, $legacySignature)) {
            Log::warning('Rejected Paystack webhook with invalid signature.');
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($rawBody, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }
        if (($event['event'] ?? null) !== 'charge.success') {
            return response()->json(['message' => 'Event ignored.'], 200);
        }

        $data = $event['data'] ?? null;
        $reference = is_array($data) ? ($data['reference'] ?? null) : null;
        if (! is_string($reference) || $reference === '' || strlen($reference) > 100) {
            Log::warning('Paystack successful event had no usable reference.');
            return response()->json(['message' => 'Invalid event data.'], 400);
        }

        try {
            $result = $this->confirmation->confirmSignedWebhook($reference, $data);
        } catch (Throwable $exception) {
            Log::error('Paystack webhook processing failed and may be retried.', ['reference' => $reference, 'exception' => $exception::class]);
            return response()->json(['message' => 'Temporary processing failure.'], 500);
        }

        if ($result === 'mismatch') {
            return response()->json(['message' => 'Transaction details mismatch.'], 400);
        }

        return response()->json(['message' => $result], 200);
    }
}
