# Stage 3: Paystack Payment Initialization — BlessedStores

Implement **Stage 3 only** of the order and payment workflow for my existing Laravel multi-vendor e-commerce application, BlessedStores.

## 1. Start by inspecting the existing implementation

Before making any changes, inspect the existing codebase to understand:

- The `orders`, `order_items`, payments, and related database tables and models.
- The existing order creation flow implemented in Stage 2.
- How orders relate to their customers, stores, products, and order items.
- The existing Paystack integration, configuration, environment variables, routes, controllers, Livewire components, and payment-related services.
- How store owners and their Paystack subaccounts are represented in the database.
- The existing platform commission configuration and calculation.
- The existing order and payment status fields.

Reuse the existing architecture, relationships, naming conventions, and code wherever possible. Do not recreate functionality already implemented.

If a required field, relationship, or integration detail is missing, identify it before deciding on the smallest necessary change.

## 2. Scope of Stage 3

Implement the process that initializes a Paystack payment for an existing unpaid order and directs the customer to Paystack's hosted checkout page.

The order must already have been created by Stage 2. Do not create another order during payment initialization.

Support both authenticated customers and guest customers using the existing checkout flow.

## 3. Payment initialization

When a customer proceeds to payment:

1. Retrieve the existing order using its identifier and the appropriate authorization rules.
2. Confirm that the order belongs to the authenticated customer or is accessible through the existing secure guest-checkout flow.
3. Confirm that the order is eligible for payment and has not already been paid.
4. Validate the order's persisted amount against its order items and applicable charges using the existing Stage 2 logic.
5. Confirm that every store participating in the order has an active, valid Paystack subaccount configured for payouts.
6. Confirm that the order's stock reservation is still valid under the existing Stage 2 implementation.
7. Generate a unique payment reference.
8. Create or update the appropriate payment record using the existing schema and relationships.
9. Initialize the transaction through Paystack's API.
10. Redirect the customer to the authorization URL returned by Paystack.

Use the server-side order amount as the source of truth. Never trust the amount, store allocations, commission, or payment reference submitted by the browser.

Handle Paystack API errors, invalid responses, network failures, and missing authorization URLs safely. Do not mark an order as paid merely because payment initialization succeeds.

## 4. Multi-store payment splitting

A single checkout can contain products belonging to multiple stores. Implement payment allocation that supports all participating stores dynamically.

For each store represented in the order:

- Calculate the store's product subtotal from the persisted order items.
- Calculate the platform commission using the existing configured commission rate and Stage 2 rules.
- Calculate the store's net allocation after deducting the applicable platform commission.
- Associate the allocation with the correct store's Paystack subaccount.

The platform commission is calculated separately for each store based on that store's product subtotal, not on the entire checkout subtotal.

Do not hardcode the number of stores, subaccount codes, commission rates, or allocation amounts.

### Cart item grouping by store

Ensure that cart items are correctly grouped by their respective stores throughout the checkout and payment initialization process.

- Group cart items by their associated `store_id` before calculating store subtotals, platform commissions, and payment allocations.
- Each store's subtotal must include only the items belonging to that store.
- Use the persisted order items created in Stage 2 as the source of truth when initializing payment. Do not recalculate payment allocations directly from potentially modified browser cart data.
- Ensure that each store's Paystack subaccount receives the correct allocation based on that store's order items and applicable platform commission.
- Support orders containing products from one store or multiple stores dynamically.
- Prevent items from being assigned to the wrong store, duplicated across store groups, or omitted from allocation calculations.
- Reconcile all store subtotals and allocations against the order's persisted totals before initializing the Paystack transaction.
- Reuse the existing cart and order relationships and grouping logic wherever possible. Do not introduce redundant database tables or relationships solely for grouping.

If Stage 2 already groups items by store when creating order items, preserve that implementation and ensure Stage 3 uses it correctly. Make any necessary corrections only if the existing grouping is incomplete or incorrect.
### Paystack API requirements

Inspect the current Paystack documentation and determine the correct supported approach for distributing one transaction among multiple subaccounts.

Do not assume that a single-subaccount split configuration supports multiple vendors. If Paystack requires a split group or another supported configuration for multi-subaccount transactions, implement the appropriate approach.

Ensure that:

- Every participating store is allocated the correct amount.
- The platform commission is retained correctly.
- Allocation amounts reconcile with the order's product subtotal and any existing applicable charges.
- Amounts are represented in the units expected by Paystack.
- Rounding is handled consistently and safely.
- The platform's own share is not accidentally paid to a vendor.
- The implementation does not silently proceed with an invalid or incomplete split.

If the existing Paystack integration cannot support the required dynamic split configuration, explain the limitation and propose the smallest compatible solution before implementing an alternative.

## 5. Transaction fees

Paystack's dashboard setting to **charge transaction fees to the customer** is enabled.

Therefore:

- Initialize the transaction using the order's actual payable base amount.
- Do not manually add an estimated Paystack processing fee to the amount sent to Paystack.
- Do not add the fee a second time in the backend.
- Do not treat the processing fee as VAT.
- Do not include the Paystack processing fee in the platform commission calculation unless the existing business rules explicitly require it.

Paystack should calculate and add its transaction fee according to the enabled dashboard setting. Do not assume that the fee can be calculated exactly from a simple percentage in the application.

## 6. Payment references and duplicate initialization

Use unique transaction references and associate each reference with the correct order and payment record.

Prevent concurrent or repeated initialization requests from creating conflicting active payment attempts or accidentally paying the same order twice.

Reuse the existing payment schema where possible. If multiple payment attempts are supported by the current design, preserve their history and ensure that only a verified successful payment can complete the order.

If the database schema needs a change, explain why and make only the necessary migration. Do not introduce redundant columns or duplicate existing relationships.

## 7. Order and payment statuses

Preserve the agreed status design:

- An order begins with the `unpaid` status.
- The `paid_at` column remains `null` until payment is confirmed through the subsequent verification/webhook stage.
- The fulfillment statuses `pending`, `processed`, and `delivered` are for paid orders only.
- Successful payment initialization does not change the order to paid.
- Do not introduce an unnecessary `packaged_at` column or status.

Use existing payment statuses and fields wherever possible. Do not implement payment verification, webhook processing, stock finalization, or fulfillment management in this stage.

## 8. Security and failure handling

- Keep Paystack secret keys on the server.
- Never expose secret keys or sensitive payment configuration to the browser.
- Use the existing authentication, authorization, and guest-order security mechanisms.
- Validate all incoming identifiers.
- Prevent users from initializing payments for other customers' orders.
- Use database transactions where appropriate for local database changes.
- Handle unsuccessful initialization without falsely marking the order as paid.
- Ensure retries do not corrupt payment records or order data.
- Do not log secret keys or sensitive payment information.

## 9. Explicit exclusions

Do not implement any of the following in Stage 3:

- Order creation or checkout redesign.
- Product quantity deduction or stock reservation redesign.
- Paystack webhook processing.
- Payment verification and final payment confirmation.
- Refunds or chargebacks.
- Store or administrator order-management interfaces.
- Customer order-history or guest-order-tracking interfaces.
- Delivery charges or shipping calculations.
- New payment gateways.
- Automated creation of test orders, customers, products, stores, or transactions.

These are outside the scope of this stage.

## 10. Manual testing and final report

I will perform the testing myself. Do not automatically seed or create test records, initiate real payments, or make live financial transactions.

After implementation, provide a concise report containing:

1. The files created or modified.
2. The payment initialization flow.
3. How dynamic multi-store allocation and platform commission work.
4. How Paystack transaction fees are handled.
5. Any migrations or environment/configuration changes required.
6. Any manual dashboard configuration required for subaccounts or split payments.
7. The exact manual testing steps I should perform.
8. Any limitations or unresolved dependencies.

Implement only Stage 3, preserve the completed Stage 1 and Stage 2 work, and do not proceed to Stage 4 without my instruction.