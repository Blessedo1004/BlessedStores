# BlessedStores — Stage 3 Report: Paystack Payment Initialization

## Scope

Stage 3 initializes Paystack payment for an order already created in Stage 2. It does not create another order, mark an order paid, verify payment, process webhooks, consume stock, or implement order-management UI.

## Files created or modified

- `app/Services/OrderPaymentInitializationService.php` — validates an existing unpaid order, its snapshots and reservations, verifies participating store accounts, creates/reuses an appropriate payment attempt, configures multi-store allocation, and initializes Paystack.
- `app/Services/PaystackService.php` — adds `createSplitGroup()` for Paystack’s split-group API; existing transaction initialization is reused.
- `resources/views/store/⚡checkout.blade.php` — adds the “Continue to Paystack” action for the previously created order, with authenticated-customer and session-bound guest access checks. Removes the old estimated processing-fee calculation from the customer amount display.

No migrations or environment variables were added. Existing `orders`, `store_orders`, `order_items`, `payments`, and `stock_reservations` schema is reused.

## Payment initialization flow

1. Stage 2 creates an unpaid order and its 30-minute stock reservations. Checkout stores the order’s public identifier in the server-side session for this checkout continuation.
2. The customer selects **Continue to Paystack**. The component reloads the order by its public identifier and checks that the authenticated customer owns it or that a guest has the matching session-bound checkout order.
3. `OrderPaymentInitializationService` takes a database lock on the order and rejects orders that are not `unpaid`, have `paid_at`, or already have a successful payment.
4. It loads persisted store orders and order items, verifies product/store associations, recomputes line and store subtotals and commissions in minor currency units, and reconciles those values against the parent order totals.
5. It checks that every participating store is operationally active, has `payment_setup_status = ready`, and has a stored Paystack subaccount code. It fetches each subaccount from Paystack and requires it to report active and verified.
6. It verifies active stock-reservation quantities still match the order’s persisted item quantities. Expired, released, missing, or mismatched reservations block payment initialization.
7. A unique payment reference and pending `payments` row are created using the order’s persisted `total_payable` and currency. Duplicate requests reuse an existing valid pending authorization URL, or are refused if an attempt has an uncertain result.
8. It creates a Paystack flat split group, then initializes the transaction with the order amount, reference, currency, callback URL, and returned `split_code`.
9. The customer is redirected only to a valid HTTPS Paystack-hosted authorization URL, and only if Paystack returns the same reference.

Order status remains `unpaid`, and `paid_at` remains null. Only the later payment-verification stage should change those fields.

## Multi-store split and commission

Allocation is derived from persisted `store_orders`, which Stage 2 populated by grouping order lines per store. The service rechecks each store order against its persisted items, verifies commission snapshots, and allocates each store’s `vendor_share` to that store’s Paystack subaccount. It does not use browser-provided cart data, amounts, subaccount codes, or commission values.

Stage 2 snapshots the 3% commission independently per store. The service converts vendor shares from NGN decimal amounts to Paystack’s minor currency unit for flat split shares. The platform’s retained amount is the remainder of the transaction amount after the vendor shares, corresponding to the persisted commissions and any configured order charges. Reconciliation failures block initialization rather than sending incomplete allocation data.

## Paystack processing fee

The Paystack Dashboard setting to charge transaction fees to the customer is enabled. Initialization sends only the order’s persisted `total_payable`; it does not add the checkout’s former hard-coded fee estimate, add VAT, or include Paystack’s fee in the platform commission. Paystack is responsible for adding and deducting its fee according to the Dashboard configuration.

## Failure and retry handling

- Store/subaccount ineligible, order mismatch, totals mismatch, or expired reservations: initialization is rejected; the order stays unpaid.
- Paystack API rejection: the payment attempt is marked failed with bounded provider context; the order remains unpaid.
- Network failure or incomplete/mismatched authorization response: the attempt is marked as outcome-unknown in provider metadata, and the customer is instructed not to retry blindly. This avoids creating conflicting attempts when Paystack may have accepted the request.
- An existing pending attempt with a saved valid Paystack authorization URL can be resumed. A pending attempt with no known safe URL is not blindly repeated.

## Dashboard/configuration prerequisites and limitations

- Use a Paystack account/environment with Split Payments enabled and permission to create split groups.
- Each store in the order must already have an active, verified Paystack subaccount and local `payment_setup_status = ready`.
- Confirm split-group policy and fee-bearer behavior in the Paystack Dashboard before live transactions.
- Paystack documentation pages were not fully retrievable during implementation. The code uses Paystack’s split-group endpoint (`POST /split`) and transaction `split_code` mechanism, but this integration should be confirmed against the current account-specific Paystack documentation/dashboard before production.
- The service creates a split group per payment attempt. If transaction initialization fails after split creation, the provider split group may remain unused; automated cleanup is not included in this stage.
- Payment verification, callbacks/webhooks, order payment finalization, stock consumption, reservation release, and late-success reconciliation remain for Stage 4.

## Manual testing steps

No test orders, payment transactions, migrations, or automated tests were run for this stage. To test manually without making a live charge:

1. Configure Paystack test credentials and use the test environment matching the store subaccounts.
2. Ensure each participating store is active, locally payment-ready, and has an active/verified test subaccount.
3. Create an unpaid order through checkout with items from one store, then repeat with items from multiple stores.
4. Confirm the checkout completion view offers **Continue to Paystack** and shows the persisted order total, without adding an estimated Paystack fee.
5. Continue and verify the hosted Paystack page opens for the same order reference.
6. In Paystack test/dashboard records, inspect that one split group contains the correct subaccount shares for the participating stores and that the transaction amount equals the persisted order payable amount in minor units.
7. Confirm the order remains `unpaid` and `paid_at` remains null after initialization. Do not treat initialization alone as payment confirmation.
8. Repeat with a disabled/unverified subaccount, expired reservation, invalid/reconciled order data, and a repeated click to confirm safe refusal/reuse behavior.

## Validation performed

- PHP syntax checks passed for `OrderPaymentInitializationService.php`, `PaystackService.php`, and the checkout Blade component.
- Blade templates compiled successfully.
- The existing named `payment.callback` route was confirmed present.
- No automated tests or migrations were run, and no real payment was initiated.
