<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function makeStoreOwner(): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => 'store'])->save();

    return $user;
}

function makeOwnedStore(User $user, array $attributes = []): Store
{
    return Store::create($attributes + [
        'user_id' => $user->id,
        'name' => 'Settlement Test Store',
        'phone_number' => '08012345678',
        'logo' => 'logo.png',
        'description' => 'A store for payment setup tests',
        'address' => '1 Main Road',
    ]);
}

function fakePaystackBanks(): array
{
    return ['status' => true, 'data' => [['name' => 'Example Bank', 'code' => '001']]];
}

it('fetches supported banks through Paystack and maps their names and codes', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake(['https://api.paystack.co/bank*' => Http::response(fakePaystackBanks())]);

    $banks = app(PaystackService::class)->fetchBanks();

    expect($banks)->toBe([['name' => 'Example Bank', 'code' => '001']]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paystack.co/bank?country=nigeria'
        && $request->hasHeader('Authorization', 'Bearer sk_test_dummy'));
});

it('resolves account details using the selected bank code', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake(['https://api.paystack.co/bank/resolve*' => Http::response([
        'status' => true,
        'data' => ['account_number' => '0123456789', 'account_name' => 'TEST ACCOUNT HOLDER'],
    ])]);

    expect(app(PaystackService::class)->resolveBankAccount('0123456789', '001'))
        ->toBe(['account_name' => 'TEST ACCOUNT HOLDER', 'account_number' => '0123456789']);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'account_number=0123456789')
        && str_contains($request->url(), 'bank_code=001'));
});

it('rejects unsupported banks and invalid account numbers before resolving', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake(['https://api.paystack.co/bank*' => Http::response(fakePaystackBanks())]);
    $user = makeStoreOwner();
    makeOwnedStore($user);
    $this->actingAs($user);

    Livewire::test('pages.stores.store-settlement')
        ->set('bankCode', '999')
        ->set('accountNumber', '123')
        ->call('resolveAccount')
        ->assertHasErrors(['accountNumber' => 'digits']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/bank/resolve'));
});

it('requires a confirmed server-side resolution and saves the returned subaccount code', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => ['account_number' => '0123456789', 'account_name' => 'TEST ACCOUNT HOLDER'],
        ]),
        'https://api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => [
                'subaccount_code' => 'ACCT_test123',
                'active' => true,
                'is_verified' => true,
            ],
        ], 201),
    ]);

    $user = makeStoreOwner();
    $store = makeOwnedStore($user);
    $this->actingAs($user);

    Livewire::test('pages.stores.store-settlement')
        ->set('bankCode', '001')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->assertSet('accountName', 'TEST ACCOUNT HOLDER')
        ->set('accountNameConfirmed', true)
        ->call('confirmAccountName')
        ->call('createSubaccount')
        ->assertHasNoErrors();

    expect($store->fresh()->paystack_subaccount_code)->toBe('ACCT_test123')
        ->and($store->fresh()->payment_setup_status)->toBe('ready')
        ->and($store->fresh()->payment_setup_completed_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paystack.co/subaccount'
        && $request['business_name'] === 'TEST ACCOUNT HOLDER'
        && $request['description'] === $store->name
        && $request['settlement_bank'] === '001'
        && $request['account_number'] === '0123456789');
});

it('invalidates cached account confirmation when the bank or account number changes', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => ['account_number' => '0123456789', 'account_name' => 'TEST ACCOUNT HOLDER'],
        ]),
    ]);

    $user = makeStoreOwner();
    makeOwnedStore($user);
    $this->actingAs($user);

    Livewire::test('pages.stores.store-settlement')
        ->set('bankCode', '001')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->set('accountNameConfirmed', true)
        ->call('confirmAccountName')
        ->set('accountNumber', '9876543210')
        ->assertSet('accountName', '')
        ->assertSet('accountNameConfirmed', false)
        ->call('createSubaccount')
        ->assertHasErrors('account');
});

it('retains an unverified subaccount for review and prevents duplicate creation', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => ['account_number' => '0123456789', 'account_name' => 'TEST ACCOUNT HOLDER'],
        ]),
        'https://api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => ['subaccount_code' => 'ACCT_review123', 'active' => true, 'is_verified' => false],
        ], 201),
    ]);

    $user = makeStoreOwner();
    $store = makeOwnedStore($user);
    $this->actingAs($user);

    $component = Livewire::test('pages.stores.store-settlement')
        ->set('bankCode', '001')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->set('accountNameConfirmed', true)
        ->call('confirmAccountName')
        ->call('createSubaccount');

    expect($store->fresh()->paystack_subaccount_code)->toBe('ACCT_review123')
        ->and($store->fresh()->payment_setup_status)->toBe('requires_review')
        ->and($store->fresh()->payment_setup_completed_at)->toBeNull();

    $component->call('createSubaccount')->assertHasErrors('account');
    Http::assertSentCount(3); // initial bank list (cached), account resolution, subaccount create
});

it('marks an ambiguous timeout for review instead of permitting a duplicate retry', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => ['account_number' => '0123456789', 'account_name' => 'TEST ACCOUNT HOLDER'],
        ]),
        'https://api.paystack.co/subaccount' => Http::failedConnection(),
    ]);

    $user = makeStoreOwner();
    $store = makeOwnedStore($user);
    $this->actingAs($user);

    $component = Livewire::test('pages.stores.store-settlement')
        ->set('bankCode', '001')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->set('accountNameConfirmed', true)
        ->call('confirmAccountName')
        ->call('createSubaccount');

    expect($store->fresh()->payment_setup_status)->toBe('requires_review');
    $component->call('createSubaccount')->assertHasErrors('account');
});

it('checks Paystack verification and marks an active verified subaccount ready', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/subaccount/ACCT_verified123' => Http::response([
            'status' => true,
            'data' => [
                'subaccount_code' => 'ACCT_verified123',
                'active' => 1,
                'is_verified' => true,
            ],
        ]),
    ]);

    $user = makeStoreOwner();
    $store = makeOwnedStore($user, [
        'paystack_subaccount_code' => 'ACCT_verified123',
        'payment_setup_status' => 'requires_review',
    ]);
    $this->actingAs($user);

    Livewire::test('pages.stores.store-settlement')
        ->call('refreshSubaccountStatus')
        ->assertHasNoErrors()
        ->assertSee('Paystack confirms this subaccount is active and verified.');

    expect($store->fresh()->payment_setup_status)->toBe('ready')
        ->and($store->fresh()->payment_setup_completed_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://api.paystack.co/subaccount/ACCT_verified123');
});

it('keeps a subaccount under review when Paystack still reports it unverified', function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response(fakePaystackBanks()),
        'https://api.paystack.co/subaccount/ACCT_pending123' => Http::response([
            'status' => true,
            'data' => [
                'subaccount_code' => 'ACCT_pending123',
                'active' => 1,
                'is_verified' => false,
            ],
        ]),
    ]);

    $user = makeStoreOwner();
    $store = makeOwnedStore($user, [
        'paystack_subaccount_code' => 'ACCT_pending123',
        'payment_setup_status' => 'requires_review',
    ]);
    $this->actingAs($user);

    Livewire::test('pages.stores.store-settlement')
        ->call('refreshSubaccountStatus')
        ->assertHasNoErrors()
        ->assertSee('Paystack still reports this subaccount as not fully active and verified.');

    expect($store->fresh()->payment_setup_status)->toBe('requires_review')
        ->and($store->fresh()->payment_setup_completed_at)->toBeNull();
});

it('does not allow a different store owner to use the settlement component', function () {
    $owner = makeStoreOwner();
    $store = makeOwnedStore($owner);
    $otherOwner = makeStoreOwner();
    $this->actingAs($otherOwner);

    Livewire::test('pages.stores.store-settlement')
        ->set('storeId', $store->id)
        ->call('resolveAccount')
        ->assertNotFound();
});

it('blocks product creation unless the selected store payment setup is ready', function () {
    $user = makeStoreOwner();
    $store = makeOwnedStore($user);
    $this->actingAs($user);

    Livewire::test('pages.products.create')
        ->set('store_id', $store->id)
        ->call('save')
        ->assertHasErrors('create');

    $store->payment_setup_status = 'ready';
    $store->save();

    Livewire::test('pages.products.create')
        ->set('store_id', $store->id)
        ->call('save')
        ->assertHasErrors('name')
        ->assertHasNoErrors('create');
});
