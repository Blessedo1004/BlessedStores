# Phase 2: Store Payment Setup and Paystack Subaccount Creation

Implement the store payment setup feature in my existing Laravel multi-vendor ecommerce application, BlessedStores.

## Context

- The application uses Laravel and Livewire.
- Store owners do not necessarily register as users before submitting store applications.
- An admin reviews and approves store applications. After approval, the application process creates or associates the appropriate user account and store.
- Stores must complete Paystack payment setup before they can add products.
- The platform already has its own Paystack account and API credentials.
- Each store needs its own Paystack subaccount so that the platform can distribute payments to multiple stores using Paystack's dynamic multi-split payments.
- The `stores` table now has these payment-related columns:
  - `paystack_subaccount_code`: nullable, unique string.
  - `payment_setup_status`: enum with `not_started`, `pending`, `processing`, `ready`, `failed`, and `requires_review`; default `not_started`.
  - `payment_setup_completed_at`: nullable timestamp.
- The store's existing `status` column has been renamed to `store_status`. Do not confuse the store's operational status with its payment setup status.

## Objective

Allow an authenticated store owner to set up their settlement bank account from their store dashboard. Resolve and confirm the bank account before creating a Paystack subaccount. Save the returned subaccount code and update the store's payment setup status.

Do not implement checkout, customer payments, order management, commissions, or dynamic payment splitting in this phase.

## 1. Inspect the existing project first

Before making changes:

- Inspect the existing store model, migrations, authentication, roles, permissions, store ownership relationships, Livewire components, layouts and dashboard navigation.
- Identify how a logged-in store owner is associated with their store.
- Reuse the project's existing conventions and components.
- Do not create duplicate models, tables, layouts or payment services unnecessarily.
- Do not modify the existing store application approval workflow unless necessary for this phase.

## 2. Store dashboard payment setup UI

Add a Payment Setup section to the existing store owner's dashboard.

The interface should:

- Display the current payment setup status.
- Allow the store owner to select a bank and enter their account number.
- Provide a button to resolve the account.
- Display the resolved account holder's name after a successful lookup.
- Require the store owner to confirm that the returned name matches their intended settlement account.
- Provide a separate action to create the Paystack subaccount after confirmation.
- Show appropriate loading states, validation messages, API errors and success messages.
- Prevent repeated submissions while a request is processing.
- Display the existing subaccount status when setup has already been completed.
- Avoid exposing the Paystack secret key or unnecessary bank details.

Follow the existing project's UI conventions and ensure the interface works on mobile and desktop.

## 3. Bank listing and account resolution

Use Paystack's official API documentation:

- List Banks: https://paystack.com/docs/api/bank/
- Resolve Account Number: https://paystack.com/docs/api/verification/#resolve-account-number

Requirements:

- Fetch supported banks from Paystack using a backend service.
- Populate the bank selector with the returned bank names and codes.
- Resolve the account using the selected bank code and account number.
- Display the returned account holder's name.
- Require explicit confirmation before allowing subaccount creation.
- If the bank or account number changes, invalidate the previous resolution and confirmation.
- Validate the account number and bank selection on the server.
- Handle API failures, invalid account details, timeouts and rate limits gracefully.
- Do not trust client-submitted account names or resolution results.

Do not hardcode bank lists or invent API response fields. Follow Paystack's current documentation.

## 4. Paystack service

Create or extend a dedicated Paystack service following the project's existing architecture.

Use the configured Paystack secret key from Laravel configuration. Never expose it to Livewire's frontend or browser JavaScript.

Implement separate methods for:

- Fetching supported banks.
- Resolving a bank account.
- Creating a subaccount.

Use Paystack's official Create Subaccount API:

https://paystack.com/docs/api/subaccount/#create

Pass the required business name, bank code, account number, and other required fields according to the current API documentation.

Use the appropriate settlement configuration for the platform's intended dynamic multi-split payment architecture. Do not invent unsupported parameters or assume that a particular percentage or flat-split configuration is required at subaccount creation.

## 5. Create and persist the subaccount

When the store owner confirms the resolved account name and requests subaccount creation:

1. Verify that the authenticated user owns or is authorized to manage the store.
2. Confirm that payment setup is not already completed.
3. Validate the submitted bank details on the server.
4. Confirm that the account resolution is still valid for the submitted bank and account number.
5. Mark payment setup as `processing`.
6. Call Paystack to create the subaccount.
7. Validate the API response and extract the actual returned subaccount code.
8. Save the subaccount code to `stores.paystack_subaccount_code`.
9. Update the payment setup status based on the response and the verification requirements established for this integration.
10. Set `payment_setup_completed_at` only when the store meets the application's defined readiness requirements.

Handle timeouts carefully. If the request times out after Paystack may have created the subaccount, do not blindly create another one. Record the issue, check for an existing subaccount where possible, and allow safe recovery.

Use appropriate database transactions for local persistence. Do not keep a database transaction open while waiting for Paystack's network request.

## 6. Status handling

Use the existing `payment_setup_status` values consistently:

- `not_started`: no setup attempt has been made.
- `pending`: setup has been requested but has not yet been processed.
- `processing`: a setup request is currently being processed.
- `ready`: the subaccount satisfies the application's confirmed requirements for payment setup.
- `failed`: setup failed and can be retried after addressing the problem.
- `requires_review`: the outcome is ambiguous or requires administrative intervention.

Do not assume that a successful subaccount creation automatically means the account is verified or eligible for every settlement operation.

Paystack may return `active` and `is_verified` fields. Handle these according to the current documentation and the platform's confirmed requirements. Do not automatically mark an unverified subaccount as unusable without establishing that this is required.

If manual verification in the Paystack Dashboard is necessary, preserve the returned subaccount code and provide an appropriate status and recovery path rather than creating duplicate subaccounts.

## 7. Security and authorization

- Only an authenticated user authorized to manage a store may modify that store's payment setup.
- Never accept a store ID from the client without checking ownership or permissions.
- Never trust client-submitted account names or confirmation flags without validating the server-side state.
- Prevent duplicate or concurrent subaccount creation attempts.
- Do not log secret keys, full bank account numbers or sensitive API payloads.
- Validate all inputs server-side.
- Use Laravel's configured HTTP client, timeouts and error handling.
- Follow existing authorization and validation conventions.

## 8. Product creation restriction

Inspect the existing product creation authorization and Livewire actions.

Ensure that a store cannot add products until `payment_setup_status` is `ready`.

Enforce this server-side in the relevant product creation actions and endpoints, not only by hiding the Add Product button.

Do not unnecessarily restrict existing store profile management or unrelated dashboard functionality.

Do not refactor the entire product system. Make only the changes necessary to enforce this requirement.

## 9. Configuration and environment

Reuse the project's existing Paystack configuration if available. Otherwise, add a suitable configuration entry that reads the secret key from `.env`.

For example, use a configuration key such as `services.paystack.secret_key`, but follow the existing project conventions if they differ.

Do not hardcode credentials. Do not modify production credentials or switch the application to live mode as part of this implementation.

## 10. Testing

Add appropriate tests following the project's existing testing framework.

Cover:

- Fetching the bank list.
- Successful account resolution.
- Invalid bank or account details.
- Account name confirmation.
- Invalidating confirmation when account details change.
- Successful subaccount creation.
- Paystack API failures and timeouts.
- Preventing duplicate subaccount creation.
- Store ownership and authorization.
- Saving the correct subaccount code and statuses.
- Preventing product creation when payment setup is not ready.
- Allowing product creation when payment setup is ready.

Mock Paystack HTTP requests in automated tests. Do not make real API calls from the test suite.

## Constraints

- Inspect the codebase before editing.
- Preserve existing application architecture and conventions.
- Avoid unnecessary migrations, dependencies, refactors and duplicated logic.
- Do not implement customer checkout, payments, commissions, order tables, or multi-split payment processing in this phase.
- Do not change the existing store approval and user account creation process unless absolutely necessary.
- Run relevant tests and report any failures.

## Expected deliverable

Implement the complete store payment setup feature, including any necessary migrations, backend service methods, Livewire UI, validation, authorization, status handling, product creation restrictions and automated tests.

At the end, summarize:
1. Files created or modified.
2. Any migrations required.
3. Environment configuration required.
4. How to test the feature locally.
5. Any Paystack Dashboard verification steps that still require manual action.