# Stage 4: Paystack Payment Verification, Webhooks, and Stock Finalization — BlessedStores

Implement **Stage 4 only** of my existing Laravel multi-vendor e-commerce application, BlessedStores.

Stages 1–3 have already been implemented. Stage 1 established the database foundation, Stage 2 implemented order creation and stock reservations, and Stage 3 implemented Paystack payment initialization and multi-store payment allocation.

Your task is to implement reliable payment verification, webhook processing, order payment confirmation, and stock finalization without rebuilding existing functionality.

## 1. Inspect the existing implementation first

Before modifying anything, inspect the existing codebase, including:

- The `orders`, `order_items`, payments, products, product variants, and related database tables and models.
- The order creation and stock reservation logic from Stage 2.
- The Paystack payment initialization and transaction reference handling from Stage 3.
- The existing order and payment status fields.
- How cart items are grouped by store and how store-specific allocations and platform commissions are calculated.
- Existing Paystack services, controllers, routes, configuration, environment variables, and logging.
- How product quantities and variant quantities are represented and updated.
- Existing database transactions, authorization rules, and guest checkout handling.

Reuse existing models, relationships, services, naming conventions, and business rules. Do not recreate tables, duplicate payment logic, or replace working Stage 1–3 functionality unnecessarily.

If an essential field or relationship is missing, identify the gap and make only the smallest necessary change.

## 2. Server-side payment verification

Implement secure server-side verification of Paystack transactions.

When a customer returns from Paystack after checkout:

1. Identify the corresponding payment attempt and order using the existing transaction reference.
2. Verify the transaction directly with Paystack's transaction verification API using the secret key on the server.
3. Confirm that the reference returned by Paystack matches the expected payment reference.
4. Confirm that the transaction belongs to the correct order and payment attempt.
5. Validate the transaction status, currency, and amount against the expected values stored for that payment attempt.
6. Confirm that the transaction has not already been processed.
7. If verification succeeds, invoke the appropriate payment confirmation and stock finalization logic.
8. If payment is pending, failed, cancelled, or otherwise unconfirmed, do not mark the order as paid.

Do not trust query parameters, browser-submitted amounts, success messages, or redirects as proof of payment.

The return route must never independently mark an order as paid without successful server-side verification.

Handle API errors, timeouts, invalid responses, unknown references, and mismatched transaction details safely.

## 3. Paystack webhook endpoint

Implement a dedicated webhook endpoint for Paystack payment events.

Requirements:

- Use the appropriate HTTP method and route configuration.
- Validate the Paystack webhook signature using the configured secret key and the exact raw request body, following Paystack's documented signature-verification procedure.
- Use a timing-safe comparison when comparing signatures.
- Reject invalid signatures without modifying orders, payments, or inventory.
- Process the relevant successful-payment event, particularly `charge.success`.
- Extract the transaction reference and retrieve the corresponding local payment attempt.
- Verify the event against the expected payment reference, order, amount, and currency.
- Use the existing payment verification service where appropriate to avoid duplicating business logic.
- Respond with appropriate HTTP status codes so Paystack can retry when processing has genuinely failed.
- Ensure that unknown, irrelevant, or already-processed events are handled safely.

Do not assume that receiving a webhook automatically proves that the payment belongs to the correct order.

Inspect Laravel's existing CSRF and route middleware configuration and exclude the webhook from CSRF verification using the appropriate approach for the installed Laravel version, without weakening CSRF protection for other routes.

Do not implement a webhook by trusting an unsigned request or by placing the Paystack secret key in frontend code.

## 4. Idempotency and duplicate processing

Paystack may send the same webhook more than once. Customers may also revisit the return URL, and multiple requests may attempt to verify the same payment concurrently.

Make payment confirmation idempotent.

- A payment must not be recorded as successful more than once.
- An order must not be transitioned to paid more than once.
- Stock must not be deducted more than once for the same order.
- Repeated successful webhooks must not duplicate payment records or financial entries.
- Concurrent verification and webhook requests must not process the same payment simultaneously in a way that corrupts state.
- Use appropriate database transactions, row locking, unique constraints, and existing processed-state fields where necessary.

Keep the implementation safe if a database operation fails halfway through processing. Payment confirmation and local stock finalization should be atomic wherever practical.

Do not rely only on checking a status before updating it; account for concurrent requests.

## 5. Successful payment and order status transitions

Preserve the agreed status design.

- Newly created orders begin with the `unpaid` status.
- The existing `paid_at` column remains `null` until successful payment is confirmed.
- After a verified successful payment, set `paid_at` and transition the order from `unpaid` to `pending`.
- The fulfillment statuses `pending`, `processed`, and `delivered` apply to paid orders.
- A failed, abandoned, or unconfirmed payment must not cause an order to become paid.
- Do not introduce a redundant `packaged_at` column or status.
- Reuse existing payment statuses and fields.

Ensure that the order's payment status and fulfillment status are updated consistently with the existing database design. Do not introduce a second, conflicting source of truth for whether an order has been paid.

If the existing schema represents payment and fulfillment statuses separately, preserve that separation.

## 6. Stock reservation finalization

Stage 2 introduced a 30-minute stock reservation. Inspect that implementation and integrate with it instead of creating a separate inventory system.

When payment is confirmed:

1. Identify every order item and its corresponding product or product variant.
2. Use the persisted order items as the source of truth.
3. Confirm that the reservation is still valid and belongs to the order.
4. Finalize the reservation using the existing reservation mechanism.
5. Deduct the reserved quantity from the correct product or variant inventory exactly once.
6. Mark the reservation as finalized or consumed using the existing schema and logic, if supported.
7. Ensure the same reserved quantity cannot be finalized by another request.

For multi-store orders, correctly finalize inventory for every participating store. Do not skip items belonging to a different store.

Do not deduct stock during payment initialization. Stock must be deducted only after successful payment confirmation.

If the current reservation implementation does not have a reliable way to distinguish reserved, released, and consumed stock, identify the problem and make the smallest necessary change.

### Expired reservations and late payments

A reservation may expire while a customer is completing payment, or a payment may be confirmed after the reservation has expired.

- Do not blindly deduct stock that is no longer available.
- Recheck inventory and reservation state using appropriate locking.
- If the original reservation is still valid, finalize it normally.
- If it has expired and sufficient stock remains available, follow a safe, consistent recovery policy that prevents overselling.
- If stock is unavailable, record the successful payment but flag the order for administrative reconciliation or refund handling, using the existing status structure where possible.
- Do not silently mark an order as fully fulfilled or pretend that inventory was successfully reserved.
- Do not implement an entire refund workflow in this stage.

Ensure that a late successful payment cannot be lost simply because the reservation expired.

## 7. Failed and expired payments

Handle unsuccessful payment attempts without corrupting order or inventory state.

- A failed or abandoned payment must not be treated as successful.
- Do not release a stock reservation merely because the customer returns to the site without completing payment.
- Release expired reservations through the existing expiration mechanism.
- Ensure that a failed payment attempt does not automatically invalidate a different payment attempt that may still be valid for the same order.
- Do not allow an unpaid order to remain eligible for payment indefinitely if its reservation has expired, unless the existing business rules explicitly allow a safe new reservation.
- Preserve payment attempt history if the existing schema supports multiple attempts.

If scheduled reservation cleanup is already implemented, reuse it. If it is not implemented and is necessary for the existing 30-minute reservation design, identify the smallest appropriate Laravel scheduled task or cleanup mechanism. Do not introduce unnecessary infrastructure.

## 8. Multi-store orders and payment allocation

Preserve the dynamic multi-store payment allocations implemented in Stage 3.

- Verify the actual Paystack transaction against the payment attempt that was initialized.
- Do not recalculate, modify, or duplicate vendor allocations during webhook processing.
- Do not create new subaccounts or split configurations in Stage 4.
- Do not assume that a successful transaction means every local store allocation has been recorded correctly unless the applicable Paystack response and existing integration establish that.
- Preserve the platform commission and store allocation records according to the existing architecture.

A single successful payment must confirm the corresponding checkout order, not create separate duplicate orders for individual stores.

If the existing Stage 3 implementation uses a Paystack split group or another supported allocation mechanism, retain that approach.

## 9. Transaction fees and amount verification

The Paystack dashboard setting to charge transaction fees to customers is enabled.

- Verify the transaction against the expected base amount stored for the payment attempt.
- Follow Paystack's documented transaction response semantics to determine which amount represents the transaction amount and how the customer-paid fee is represented.
- Do not assume the fee-adjusted customer charge must equal the base order amount.
- Do not reject valid transactions simply because Paystack charged the customer its processing fee on top of the base amount.
- Do not add the Paystack fee a second time.
- Do not include the customer-paid processing fee in the platform commission calculation unless the existing business rules explicitly require it.
- Validate currency and transaction status as well as the amount.

Use the expected amount from the server-side payment record, not from the browser or webhook payload alone.

## 10. Logging and failure recovery

Add appropriate logging for:

- Invalid webhook signatures.
- Unknown payment references.
- Amount, currency, or reference mismatches.
- Paystack API failures and verification errors.
- Duplicate events and already-processed payments.
- Stock finalization failures.
- Successful payment confirmation.
- Late successful payments requiring administrative reconciliation.

Do not log secret keys or unnecessarily expose customers' personal information.

If payment confirmation succeeds with Paystack but local processing fails, preserve enough information for safe retry and reconciliation. Return a response that allows Paystack to retry transient webhook-processing failures rather than acknowledging an event that was not successfully handled.

Avoid marking an event permanently processed before the corresponding business operations have completed successfully.

## 11. Explicit exclusions

Do not implement the following in Stage 4:

- A new checkout or order creation flow.
- Changes to the completed Stage 3 payment initialization unless a specific defect prevents Stage 4 from working.
- New payment gateways.
- Refund processing or automated refund initiation.
- Store or administrator order-management interfaces.
- Customer order-history or guest order-tracking interfaces.
- Delivery, shipping, or tax calculations.
- Changes to platform commission rates or multi-store allocation rules.
- Automated creation of test customers, products, stores, orders, or transactions.
- Real payments or live financial transactions.

These tasks are outside the scope of this stage.

## 12. Manual testing and final report

I will perform the testing myself. Do not automatically seed or create test records, initiate real payments, or make live financial transactions.

After implementation, provide a concise report covering:

1. Files created and modified.
2. How server-side payment verification works.
3. How the Paystack webhook signature is validated.
4. How duplicate events and concurrent requests are handled.
5. How order status and `paid_at` are updated.
6. How stock reservations are finalized and expired reservations are handled.
7. Any migrations, environment variables, or Paystack dashboard changes required.
8. How to configure the webhook URL in Paystack's dashboard.
9. The exact manual test scenarios I should perform.
10. Any unresolved dependencies, assumptions, or limitations.

Implement Stage 4 only. Preserve the completed Stages 1–3, do not proceed to Stage 5, and do not claim that payment verification or webhook processing is complete unless the corresponding implementation actually exists.