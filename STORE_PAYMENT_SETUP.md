# Store Payment Setup: Code Guide

This note explains the Livewire settlement component at [resources/views/pages/stores/⚡store-settlement.blade.php](resources/views/pages/stores/⚡store-settlement.blade.php).

## `ownedStore()`

`ownedStore()` is a private helper method defined inside the component class; it is not supplied by Laravel or Livewire. It does two checks:

1. `auth()->user()?->can('store')` confirms the current signed-in user has the store role. The null-safe operator avoids calling `can()` if there is no authenticated user; `abort_unless(..., 403)` rejects anyone who does not pass.
2. `auth()->user()->stores()->whereKey($this->storeId)->firstOrFail()` finds the selected store through the signed-in user's `stores()` relationship. Because the query starts from that relationship, a client-submitted `storeId` cannot be used to access another owner's store. If the selected ID is not one of the user's stores (or no store was selected), Laravel returns a 404.

The method returns the authorized `Store` model. Payment actions call it before reading or changing settlement information, for example:

- `resolveAccount()` checks ownership before requesting account resolution.
- `confirmAccountName()` checks ownership before confirming the cached resolution.
- `createSubaccount()` checks ownership before creating the remote subaccount.

This is important because Livewire public properties, including `$storeId`, are sent by the browser and must not be treated as trusted authorization data.

## Setup flow

1. `mount()` permits store-role users, selects their first store, and loads bank choices using `PaystackService`.
2. The owner selects a bank and types the 10-digit account number.
3. `resolveAccount()` validates both values on the server, checks the bank against Paystack's current bank list, then resolves the account using Paystack. The resolved name and a fingerprint of bank code plus account number are cached server-side for 10 minutes.
4. The owner confirms the resolved name. `confirmAccountName()` ensures the cached resolution still matches the submitted bank and account number before recording that confirmation.
5. `createSubaccount()` requires that valid server-side resolution and confirmation, marks payment setup as `processing`, then requests subaccount creation from Paystack.
	- Paystack's `business_name` is populated with the verified account holder name from the server-side resolution.
	- The store name is sent as the subaccount `description` (the API's supported alias-like field).
6. A returned subaccount code is persisted. The status is set to `ready` only if Paystack reports both `active` and `is_verified`; otherwise the code is kept and the status becomes `requires_review` so the owner cannot accidentally create a duplicate.
7. A connection timeout is ambiguous because Paystack could have processed the request even though the app did not receive a response. The component marks that outcome `requires_review` and does not allow an automatic retry.

The product creation component separately checks ownership and `payment_setup_status === 'ready'` on the server; hiding an Add Product link in the page is not the security control.

## Important helpers

- `ensureSetupCanStart()` prevents a new attempt when the store is already ready, processing, under review, or already has a saved subaccount code.
- `validResolution()` retrieves the server-side cached account resolution and ensures it still matches the current bank/account fingerprint.
- `saveSetupOutcome()` persists the status and code in a short local database transaction after the Paystack network request completes.
- `resetAccountResolution()` removes the cached resolution and confirmation when the user changes account details.

Paystack's secret key stays in backend Laravel configuration (`services.paystack.secret_key`); it is not exposed in component properties or rendered markup.

### Subaccount display names

Paystack's documented Create Subaccount API accepts `business_name` and `description`; it does not document an `alias` request field. The integration uses the resolved bank account holder for `business_name` and the store's name for `description`, which keeps the legal account name separate from the store label. This applies to newly created subaccounts. For an existing subaccount, update its business name and description in the Paystack Dashboard (or use Paystack's documented Update Subaccount API); changing this local code does not retroactively change an already-created subaccount.

## Paystack API calls: detailed breakdown

The settlement page delegates provider communication to [`app/Services/PaystackService.php`](app/Services/PaystackService.php). The component itself never constructs an HTTP request or receives the secret key. It calls the service methods below.

### 1. `fetchBanks()` — populate and validate the bank selector

**Paystack endpoint:** `GET /bank?country=nigeria` (the country defaults to `nigeria`).

The page calls this when it mounts to populate the bank selector. It also calls the method during account resolution and again before subaccount creation, ensuring the selected code is in Paystack's supported bank list.

The service caches the parsed bank list under `paystack.banks.nigeria` for 12 hours. While the cache is valid, the calls above use the cached list and do not each make a new Paystack request. On a cache miss, the method:

1. Makes the authenticated request through the private `request()` helper.
2. Reads Paystack's `data` array.
3. Keeps entries that contain string `name` and `code` fields, and returns only those fields to Livewire.

The bank list is public reference data, not an account or user record. A bank code selected in the browser is still checked against this backend list before it is used.

### 2. `resolveBankAccount()` — verify the entered account number

**Paystack endpoint:** `GET /bank/resolve` with query parameters `account_number` and `bank_code`.

The component calls this after server-side validation requires a selected bank and exactly 10 account-number digits. Before the lookup, it fetches/reads the bank list and rejects a code not in that list.

The service expects Paystack to return:

- `data.account_name`: the bank-resolved account holder name.
- `data.account_number`: the account number Paystack resolved.

It returns these values to the component. The component additionally compares the returned number with the submitted number. It does **not** accept an account name supplied by the browser. Instead, it stores the service's result in the server-side cache for 10 minutes, keyed to the signed-in user and store. A SHA-256 fingerprint of the bank code plus account number ensures the cached resolution matches the current details. The owner must explicitly confirm the returned name before proceeding.

Changing the selected bank or account number removes this cached resolution and its confirmation. Expired or mismatched cached data must be resolved again.

### 3. `createSubaccount()` — register a Paystack settlement subaccount

**Paystack endpoint:** `POST /subaccount` with a JSON body.

Before this method is called, the component rechecks store ownership, validates the bank and account number, verifies the server-cached resolution and its confirmation, and marks the store's local `payment_setup_status` as `processing` in a short database transaction. It does not hold that transaction open during the network request.

The service builds a payload from the allowed fields:

| Paystack field | Source in this integration | Purpose |
| --- | --- | --- |
| `business_name` | Paystack-resolved account holder name | Names the account/subaccount using the confirmed bank account holder rather than assuming it matches the store's trading name. |
| `settlement_bank` | Selected Paystack bank `code` | Identifies the destination bank. |
| `account_number` | Validated account number | Identifies the settlement account. |
| `description` | Store name | Human-readable store label; this is the documented description field, not a separate `alias` API field. |
| `primary_contact_name` | Store owner's user name | Optional subaccount contact information. |
| `primary_contact_email` | Store owner's user email | Optional subaccount contact information. |
| `primary_contact_phone` | Store phone number | Optional subaccount contact information. |
| `percentage_charge` | `services.paystack.subaccount_percentage_charge` | The configured percentage charge. It defaults to 3 and can be overridden with `PAYSTACK_SUBACCOUNT_PERCENTAGE_CHARGE`. Paystack documents this as the percentage the main integration receives from payments to the subaccount. This setting configures the subaccount; it does not implement checkout or payment splitting in this phase. |

Optional contact and description fields are omitted if empty. The service checks Paystack's response for a non-empty `data.subaccount_code` before the component saves it. If the response is incomplete, the request is treated as ambiguous and moved to `requires_review`, rather than retried automatically.

### 4. `fetchSubaccount()` — refresh Paystack verification status

**Paystack endpoint:** `GET /subaccount/{subaccount_code}`.

The component calls this when the owner selects **Check Paystack verification status** for a saved subaccount. The service first validates the saved code's expected `ACCT_...` format, then requests that exact subaccount from Paystack and confirms the response's `data.subaccount_code` matches the requested code.

The component checks Paystack's returned `active` and `is_verified` fields:

- If both indicate true, it saves `payment_setup_status = ready` and sets `payment_setup_completed_at`.
- Otherwise it retains the subaccount code and keeps the status at `requires_review`; it does not create another subaccount.

This refresh is safe to repeat: it is a read-only fetch and does not create or update a Paystack resource. If Paystack is unreachable, the local status is left unchanged and the page tells the owner to try again later.

### The shared private `request()` helper

All of the methods above use `request()` for consistent transport and error handling. It:

1. Reads the secret from `config('services.paystack.secret_key')` (configured from `PAYSTACK_SECRET_KEY`) and fails closed if it is missing.
2. Sends requests to the configured Paystack base URL with `Authorization: Bearer …`, JSON headers, a 3-second connection timeout, and a 10-second overall HTTP timeout. The secret key stays on the server.
3. Uses a query string for GET parameters and JSON for POST bodies.
4. Requires both a successful HTTP status and Paystack JSON `status: true`.
5. Throws `PaystackApiException` for provider/API rejections, carrying the HTTP status and a bounded provider message. Ten-digit account numbers echoed in that message are redacted before the message is logged or shown.

Connection failures/timeouts raise Laravel's `ConnectionException`, handled separately from Paystack API rejections. In particular, a timeout during **subaccount creation** may mean Paystack created the account but the response was lost, so the app changes local status to `requires_review` and blocks another create attempt. A timeout during **status refresh** is a read failure, so the existing local status is preserved.

### Methods in the same service that this settlement page does not call

`initializeTransaction()` (`POST /transaction/initialize`) and `verifyTransaction()` (`GET /transaction/verify/{reference}`) are retained for the application's existing customer-payment flow. They are not part of this store settlement setup page and do not create, verify, or fetch a store subaccount.
