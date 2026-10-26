# BlessedStores — Stage 2 Report

## Scope

This stage covers order creation, stock reservation, and guest-cart merge handling only. Payment initialization, payment verification, and order-tracking UI remain explicitly out of scope.

## 1. Files created or modified

- app/Services/OrderCreationService.php
- app/Services/CartService.php
- app/Http/Controllers/Auth/AuthController.php
- resources/views/store/⚡checkout.blade.php
- app/Models/Order.php
- app/Models/StoreOrder.php
- app/Models/OrderItem.php
- app/Models/Payment.php
- app/Models/StockReservation.php
- app/Models/User.php
- app/Models/Store.php
- database/migrations/2026_10_26_100000_create_orders_table.php
- database/migrations/2026_10_26_100001_create_store_orders_table.php
- database/migrations/2026_10_26_100002_create_order_items_table.php
- database/migrations/2026_10_26_100003_create_payments_table.php
- database/migrations/2026_10_26_100004_create_stock_reservations_table.php
- database/migrations/2026_10_26_100005_add_unpaid_order_status.php

## 2. Migrations added and their purpose

- create_orders_table: parent-order record with customer contact, delivery details, totals, currency, and order status fields.
- create_store_orders_table: one order slice per store, with subtotal, commission rate, commission value, and vendor share.
- create_order_items_table: immutable product/variant snapshot lines for historical orders.
- create_payments_table: payment-attempt records keyed by Paystack reference and payment lifecycle state.
- create_stock_reservations_table: temporary inventory hold records for products and variants.
- add_unpaid_order_status: adds the required unpaid state to the order-status enum while preserving the later pending/processed/delivered lifecycle.

No packaged_at field was introduced. Fulfilment state stays on the parent order record via orders.status.

## 3. How order creation and stock reservation work

- The checkout flow validates the cart server-side, and reloads current prices and inventory from the database before creating anything.
- The service checks product availability, variant availability, stock against active reservations, and store payment readiness before allowing checkout.
- It enforces the existing minimum order threshold of ₦100.
- The order is created in a database transaction with status = unpaid and paid_at = null.
- Store-level records are created for each vendor slice, and line-item snapshots are stored with the historical unit price, line subtotal, and commission snapshots.
- A reservation record is created for every product/variant line with expires_at = now() + 30 minutes and status = reserved.
- Reservation totals are checked against active reservations before confirming the checkout, so concurrent checkouts cannot oversell the same inventory.
- If any validation fails, the transaction rolls back and the order is not left partially created.
- Cart items are cleared only after the order and reservations have successfully been committed.

## 4. How guest and authenticated carts are handled

- Guest carts are stored in the existing cart token / cache pattern and preserve base-product and variant entries.
- Authenticated carts continue to use the user-specific cart table for base product quantities.
- The guest-login merge path reloads guest cart data, validates current product and variant stock, merges valid quantities into the authenticated cart, and only clears guest cache data after the merge succeeds.
- If the merge fails, the guest cart is left intact rather than being discarded.
- The same checkout validation logic applies to both guest and authenticated customers; guest orders retain customer details and user_id = null.

## 5. Tests run and results

No automated tests were run. No migration commands were run. This matches the project requirement to stop after Stage 2 and to avoid destructive database operations while implementing the requested foundation.

## 6. Database-engine limitations and unresolved issues

- SQLite in the local test environment has limited row-locking and transaction semantics compared with MySQL/PostgreSQL.
- The application avoids relying on database-only locking behavior beyond the available transaction model and uses `lockForUpdate()` for the relevant reads and `expires_at` checks to keep stock availability checks consistent.
- Active reservations are considered valid only when status = reserved and expires_at > now(). This prevents expired reservations from blocking stock indefinitely.
- This stage intentionally does not implement payment callbacks, inventory consumption on successful payments, or order fulfillment transitions.

## 7. Temporary checkout behavior until Stage 3

The checkout behavior is intentionally temporary and safe:

- create an unpaid order record;
- reserve stock for 30 minutes;
- validate against real server-side inventory and store readiness;
- return a temporary order reference to the customer;
- do not initialize Paystack;
- do not create a fake payment record;
- do not mark the order as paid;
- do not decrement physical inventory;
- do not auto-transition order status.

This preserves a durable order record without falsely claiming that payment was completed.
