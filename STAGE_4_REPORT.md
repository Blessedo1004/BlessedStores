# Stage 4 Report: Paystack Verification, Webhooks, and Stock Finalization

## Implementation summary

Implemented server-side Paystack transaction verification, signed webhook confirmation, idempotent payment confirmation, stock reservation finalization, scheduled cleanup for expired reservations, a payment success receipt page, and customer order confirmation email.

## Files created or modified

- `app/Http/Controllers/PaymentController.php` — replaced the local mock success path; verifies callback references through the shared confirmation service; handles webhook requests.
- `app/Services/PaymentConfirmationService.php` — verifies transactions against the saved payment attempt and atomically confirms payment and finalizes inventory.
- `app/Services/PaystackService.php` — validates webhook HMAC signatures using the exact raw request body and `hash_equals`.
- `app/Mail/OrderPaymentConfirmationEmail.php` — sends the customer a payment and order confirmation email.
- `resources/views/emails/order-payment-confirmation.blade.php` — customer order email template.
- `resources/views/store/⚡payment-successful.blade.php` — payment receipt page with order details and a home button.
- `routes/web.php` — added `POST /payment/paystack/webhook`.
- `app/Http/Controllers/PaymentController.php` — redirects verified customer callbacks to the receipt and serves persisted order details.
- `bootstrap/app.php` — excludes only the Paystack webhook path from CSRF validation.
- `app/Console/Commands/ReleaseExpiredStockReservations.php` — releases expired reservations for unpaid orders.
- `routes/console.php` — schedules reservation cleanup every five minutes.

No migration was added. `PAYSTACK_SECRET_KEY` must be set on the server. `PAYSTACK_WEBHOOK_SECRET` is optional for the newer timestamped Paystack signature format; if the dashboard sends that format, it must be configured.

## Verification and confirmation flow

The customer callback accepts the Paystack reference and calls Paystack's verify API from the server. The service requires a matching local payment attempt, exact reference, successful transaction status, matching currency, and matching base amount in minor units. For customer-paid fees, it validates the saved base amount against `requested_amount` and checks the gross charge against Paystack's reported `fees` when supplied. It does not use browser-submitted amounts or trust the callback redirect as payment proof.

Paystack documents `data.amount` as the transaction amount in the currency's lower denomination and identifies `data.status` as the transaction status. The implementation compares that amount to the persisted expected amount; it does not add the customer-paid processing fee. See [Paystack Verify Payments](https://paystack.com/docs/payments/verify-payments/).

## Webhook

Endpoint: `POST https://your-domain/payment/paystack/webhook`

The endpoint validates Paystack's signed webhook against the exact raw request body before any payment or inventory changes. It supports timestamped `Paystack-Signature` using `PAYSTACK_WEBHOOK_SECRET` (`whsec_...`) and legacy `x-paystack-signature` using `PAYSTACK_SECRET_KEY`; comparisons are timing safe and timestamped signatures are limited to a five-minute window. Only `charge.success` is processed. A valid signed event is checked against the saved local reference, base order amount, currency, and reported fee and is processed without an outbound verification API request. This lets the webhook confirm a completed payment even when the app cannot reach Paystack's API, as long as Paystack can reach the webhook URL. The browser callback still uses Paystack's server-side verify API.

Unknown references and irrelevant events are acknowledged safely. Local processing failure returns an error response so Paystack can retry. Amount, currency, and reference mismatches are logged and rejected. Confirmed payment remains recorded if email delivery fails; a later duplicate callback/webhook retries the email without deducting stock again.

## Idempotency, statuses, and inventory

The service locks the payment and order rows inside a database transaction. It also locks reservations and the affected product and variant rows. A previously successful payment is treated as a duplicate, preventing a second payment transition or stock deduction.

On successful payment, the payment is marked `success`, `paid_at` is set on both payment and order, and the order moves from `unpaid` to fulfillment status `pending` when stock is finalized. Valid reservations are consumed and inventory is deducted once across all persisted order items and stores. A verified customer callback redirects to `/payment/successful?reference=...`; the page loads order details from the persisted payment/order relationship, shows line items, totals, order status and references, and has a button back to the home page. The customer email is taken from the saved order, not the webhook/browser payload.

If a reservation expired, the service checks stock while holding inventory locks and excludes other active reservations. If enough stock remains, it recovers and consumes the reservation. If stock is unavailable, the successful payment is still recorded, the order remains outside fulfillment, and payment metadata is flagged with `reconciliation_required` for administrative handling.

## Expiration cleanup

Laravel schedules `stock-reservations:release-expired` every five minutes. The host must run Laravel's scheduler, typically with `php artisan schedule:run` once per minute. Expired reservations belonging to unpaid orders are marked `released`; successful or paid orders are not cleaned up.

## Paystack and deployment configuration

1. Set the server-side `PAYSTACK_SECRET_KEY` in the environment. If Paystack sends timestamped `Paystack-Signature` headers, set `PAYSTACK_WEBHOOK_SECRET` to the webhook signing secret from Dashboard > Developers > API configuration. Legacy `x-paystack-signature` uses the API secret.
2. In the Paystack Dashboard webhook settings, set the URL to `https://your-domain/payment/paystack/webhook`.
3. Configure the host scheduler to invoke `php artisan schedule:run` every minute for reservation cleanup.

No dashboard allocation, subaccount, or split configuration was changed by Stage 4.

## Manual scenarios

Please verify manually using Paystack test mode and test data:

1. Successful payment confirms one payment, sets `paid_at`, changes the order to `pending`, deducts stock once, consumes reservations, redirects to the receipt, and sends one confirmation email.
2. Pending, failed, abandoned, or cancelled payment does not mark the order paid or deduct inventory.
3. Invalid or missing webhook signature is rejected without changing payment, order, or stock.
4. Unknown references and irrelevant webhook events do not modify local records.
5. Wrong reference, amount, or currency is rejected and logged.
6. Repeating the callback and `charge.success` webhook does not create duplicate confirmation or stock deduction.
7. Concurrent callback and webhook delivery confirms and deducts stock only once.
8. An expired reservation with enough remaining stock is safely recovered and finalized.
9. An expired reservation without enough stock records payment and sets the reconciliation flag while leaving the order outside fulfillment.
10. Running the scheduled cleanup releases expired reservations on unpaid orders.
11. Simulate Paystack API unavailability after a successful charge and confirm that a valid `charge.success` webhook alone confirms the order without a verification API request.
12. Check the receipt page displays the persisted order details and the home button works.
13. Confirm email delivery to the saved customer email, and verify a repeated event does not duplicate an already-sent email.

## Checks and limitations

PHP syntax checks, `php artisan route:list --path=payment`, Blade compilation, and `git diff --check` completed during implementation. Automated tests and live or test payments were not run. The webhook must be publicly reachable over HTTPS, and the correct signing secret format must be configured. Administrative reconciliation is represented by payment metadata; no admin interface or refund workflow is included in this stage.
