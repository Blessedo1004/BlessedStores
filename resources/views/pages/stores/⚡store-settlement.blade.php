<?php

use App\Models\Store;
use App\Exceptions\PaystackApiException;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component
{
    #[Title('Payment Setup')]

    public ?int $storeId = null;
    public string $bankCode = '';
    public string $accountNumber = '';
    public string $accountName = '';
    public bool $accountNameConfirmed = false;
    public array $banks = [];
    public ?string $notice = null;
    public ?string $noticeType = null;

    public function mount(PaystackService $paystack): void
    {
        abort_unless(auth()->user()?->can('store'), 403);
        $this->storeId = auth()->user()->stores()->orderBy('name')->value('id');
        $this->loadBanks($paystack);
        $this->refreshResolvedAccount();
    }

    public function updatedStoreId(): void
    {
        $this->resetAccountResolution();
        $this->clearNotice();
        $this->refreshResolvedAccount();
    }

    public function updatedBankCode(): void
    {
        $this->resetAccountResolution();
        $this->clearNotice();
    }

    public function updatedAccountNumber(): void
    {
        $this->resetAccountResolution();
        $this->clearNotice();
    }

    public function resolveAccount(PaystackService $paystack): void
    {
        $store = $this->ownedStore();
        $this->ensureSetupCanStart($store);
        $this->validate([
            'bankCode' => ['required', 'string', 'max:20'],
            'accountNumber' => ['required', 'digits:10'],
        ]);

        try {
            $banks = $paystack->fetchBanks();
            if (! collect($banks)->contains(fn ($bank) => $bank['code'] === $this->bankCode)) {
                throw ValidationException::withMessages(['bankCode' => 'Select a supported bank.']);
            }

            $resolved = $paystack->resolveBankAccount($this->accountNumber, $this->bankCode);
            if ($resolved['account_number'] !== $this->accountNumber) {
                throw new RuntimeException('Paystack returned account details that did not match the request.');
            }

            Cache::put($this->resolutionKey($store), [
                'fingerprint' => $this->detailsFingerprint(),
                'account_name' => $resolved['account_name'],
                'confirmed' => false,
            ], now()->addMinutes(10));
            $this->accountName = $resolved['account_name'];
            $this->accountNameConfirmed = false;
            $this->setNotice('Account resolved. Confirm the account holder name before creating the settlement account.', 'success');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (PaystackApiException $exception) {
            Log::warning('Paystack account resolution was rejected.', [
                'store_id' => $store->id,
                'http_status' => $exception->statusCode,
                'provider_message' => $exception->providerMessage,
            ]);
            $this->resetAccountResolution();
            $this->addError('account', 'Paystack could not resolve this account: ' . $exception->providerMessage);
        } catch (Throwable $exception) {
            Log::warning('Paystack account resolution failed unexpectedly.', [
                'store_id' => $store->id,
                'exception' => $exception::class,
            ]);
            $this->resetAccountResolution();
            $this->addError('account', 'Unable to resolve this account right now. Check the details or try again later.');
        }
    }

    public function confirmAccountName(): void
    {
        $store = $this->ownedStore();
        $resolution = $this->validResolution($store);
        if (! $this->accountNameConfirmed) {
            throw ValidationException::withMessages(['accountNameConfirmed' => 'Confirm that the account holder name is correct.']);
        }

        $resolution['confirmed'] = true;
        Cache::put($this->resolutionKey($store), $resolution, now()->addMinutes(10));
        $this->setNotice('Account name confirmed. You can now create the Paystack settlement account.', 'success');
    }

    public function createSubaccount(PaystackService $paystack): void
    {
        $store = $this->ownedStore();
        $this->ensureSetupCanStart($store);
        $this->validate([
            'bankCode' => ['required', 'string', 'max:20'],
            'accountNumber' => ['required', 'digits:10'],
            'accountNameConfirmed' => ['accepted'],
        ]);

        $resolution = $this->validResolution($store);
        if (! ($resolution['confirmed'] ?? false)) {
            throw ValidationException::withMessages(['accountNameConfirmed' => 'Resolve and confirm the account holder name before continuing.']);
        }

        try {
            $banks = $paystack->fetchBanks();
            if (! collect($banks)->contains(fn ($bank) => $bank['code'] === $this->bankCode)) {
                throw ValidationException::withMessages(['bankCode' => 'Select a supported bank.']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->addError('account', 'Unable to verify the selected bank with Paystack. Please try again.');
            return;
        }

        // Persist processing before the remote call and serialize concurrent attempts.
        DB::transaction(function () use (&$store): void {
            $store = auth()->user()->stores()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $this->ensureSetupCanStart($store);
            $store->payment_setup_status = 'processing';
            $store->save();
        });

        try {
            $response = $paystack->createSubaccount([
                'business_name' => $resolution['account_name'],
                'settlement_bank' => $this->bankCode,
                'account_number' => $this->accountNumber,
                'description' => $store->name,
                'primary_contact_name' => $store->user?->name,
                'primary_contact_email' => $store->user?->email,
                'primary_contact_phone' => $store->phone_number,
            ]);

            $data = data_get($response, 'data');
            $subaccountCode = data_get($data, 'subaccount_code');
            if (! is_array($data) || ! is_string($subaccountCode) || $subaccountCode === '') {
                $this->saveSetupOutcome($store->id, 'requires_review');
                $this->setNotice('Paystack may have created your subaccount, but its response was incomplete. Contact Blessed Stores Support so our team can verify and review it in the Paystack Dashboard. Please do not retry.', 'warning');
                return;
            }

            $isActive = in_array(data_get($data, 'active'), [true, 1, '1'], true);
            $isVerified = in_array(data_get($data, 'is_verified'), [true, 1, '1'], true);
            $ready = $isActive && $isVerified;
            $this->saveSetupOutcome($store->id, $ready ? 'ready' : 'requires_review', $subaccountCode);
            Cache::forget($this->resolutionKey($store));
            $this->accountName = '';
            $this->accountNameConfirmed = false;
            $this->setNotice($ready
                ? 'Settlement account setup is complete.'
                : 'Your subaccount was created and needs verification. Contact Blessed Stores Support so our team can verify and review it in the Paystack Dashboard. Product listings will be available after review.', $ready ? 'success' : 'warning');
        } catch (ConnectionException $exception) {
            // The remote request may have succeeded; never retry an ambiguous result blindly.
            $this->saveSetupOutcome($store->id, 'requires_review');
            $this->setNotice('Paystack did not confirm whether your subaccount was created. Contact Blessed Stores Support so our team can verify and review the result in the Paystack Dashboard. Please do not retry.', 'warning');
            Log::warning('Paystack subaccount request timed out; outcome requires review.', [
                'store_id' => $store->id,
            ]);
        } catch (PaystackApiException $exception) {
            $this->saveSetupOutcome($store->id, 'failed');
            Log::warning('Paystack rejected subaccount creation.', [
                'store_id' => $store->id,
                'http_status' => $exception->statusCode,
                'provider_message' => $exception->providerMessage,
            ]);
            $this->addError('account', 'Paystack rejected the settlement setup: ' . $exception->providerMessage);
        } catch (Throwable $exception) {
            $this->saveSetupOutcome($store->id, 'failed');
            Log::error('Unexpected error while creating Paystack subaccount.', [
                'store_id' => $store->id,
                'exception' => $exception::class,
            ]);
            $this->addError('account', 'Paystack could not create the settlement account. Review the details and try again.');
        }
    }

    public function retryBankList(PaystackService $paystack): void
    {
        $this->loadBanks($paystack);
    }

    public function refreshSubaccountStatus(PaystackService $paystack): void
    {
        $store = $this->ownedStore();
        if (! $store->paystack_subaccount_code) {
            throw ValidationException::withMessages(['account' => 'There is no Paystack subaccount to check yet.']);
        }

        try {
            $subaccount = $paystack->fetchSubaccount($store->paystack_subaccount_code);
            $isActive = in_array(data_get($subaccount, 'active'), [true, 1, '1'], true);
            $isVerified = in_array(data_get($subaccount, 'is_verified'), [true, 1, '1'], true);

            if ($isActive && $isVerified) {
                $this->saveSetupOutcome($store->id, 'ready', $store->paystack_subaccount_code);
                $this->setNotice('Paystack confirms this subaccount is active and verified. Payment setup is ready.', 'success');
                return;
            }

            $this->saveSetupOutcome($store->id, 'requires_review', $store->paystack_subaccount_code);
            $this->setNotice('Paystack still reports this subaccount as not fully active and verified. Contact Blessed Stores Support if you have completed verification in the Paystack Dashboard.', 'warning');
        } catch (ConnectionException $exception) {
            $this->setNotice('Could not reach Paystack to check the status. Please try again later.', 'warning');
            Log::warning('Paystack subaccount status check timed out.', ['store_id' => $store->id]);
        } catch (PaystackApiException $exception) {
            $this->addError('account', 'Paystack could not check the subaccount: ' . $exception->providerMessage);
            Log::warning('Paystack rejected subaccount status check.', [
                'store_id' => $store->id,
                'http_status' => $exception->statusCode,
                'provider_message' => $exception->providerMessage,
            ]);
        } catch (Throwable $exception) {
            $this->addError('account', 'Unable to check the subaccount status right now. Please try again later.');
            Log::error('Unexpected error while checking Paystack subaccount status.', [
                'store_id' => $store->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function loadBanks(PaystackService $paystack): void
    {
        try {
            $this->banks = $paystack->fetchBanks();
        } catch (Throwable $exception) {
            $this->banks = [];
            $this->addError('banks', 'Bank options are temporarily unavailable. Please try again.');
        }
    }

    private function ownedStore(): Store
    {
        abort_unless(auth()->user()?->can('store'), 403);
        return auth()->user()->stores()->whereKey($this->storeId)->firstOrFail();
    }

    private function ensureSetupCanStart(Store $store): void
    {
        if ($store->paystack_subaccount_code || in_array($store->payment_setup_status, ['ready', 'processing', 'requires_review'], true)) {
            throw ValidationException::withMessages(['account' => 'This store already has a settlement setup in progress or requires review.']);
        }
    }

    private function validResolution(Store $store): array
    {
        $resolution = Cache::get($this->resolutionKey($store));
        if (! is_array($resolution) || ($resolution['fingerprint'] ?? null) !== $this->detailsFingerprint()) {
            throw ValidationException::withMessages(['account' => 'Resolve the current bank account details again before continuing.']);
        }

        $this->accountName = $resolution['account_name'];
        return $resolution;
    }

    private function saveSetupOutcome(int $storeId, string $status, ?string $subaccountCode = null): void
    {
        DB::transaction(function () use ($storeId, $status, $subaccountCode): void {
            $store = auth()->user()->stores()->whereKey($storeId)->lockForUpdate()->firstOrFail();
            if ($subaccountCode !== null) {
                $store->paystack_subaccount_code = $subaccountCode;
            }
            $store->payment_setup_status = $status;
            $store->payment_setup_completed_at = $status === 'ready' ? now() : null;
            $store->save();
        });
    }

    private function resolutionKey(Store $store): string
    {
        return 'store-payment-resolution:' . auth()->id() . ':' . $store->id;
    }

    private function detailsFingerprint(): string
    {
        return hash('sha256', $this->bankCode . ':' . $this->accountNumber);
    }

    private function resetAccountResolution(): void
    {
        if ($this->storeId && auth()->check()) {
            Cache::forget('store-payment-resolution:' . auth()->id() . ':' . $this->storeId);
        }
        $this->accountName = '';
        $this->accountNameConfirmed = false;
    }

    private function refreshResolvedAccount(): void
    {
        if (! $this->storeId) {
            return;
        }
        $resolution = Cache::get('store-payment-resolution:' . auth()->id() . ':' . $this->storeId);
        if (is_array($resolution) && ($resolution['fingerprint'] ?? null) === $this->detailsFingerprint()) {
            $this->accountName = $resolution['account_name'];
            $this->accountNameConfirmed = (bool) ($resolution['confirmed'] ?? false);
        }
    }

    private function clearNotice(): void
    {
        $this->notice = null;
        $this->noticeType = null;
    }

    private function setNotice(string $message, string $type): void
    {
        $this->notice = $message;
        $this->noticeType = $type;
    }

    public function render(): mixed
    {
        return view('pages.stores.⚡store-settlement', [
            'stores' => auth()->user()->stores()->orderBy('name')->get(),
            'currentStore' => $this->storeId ? auth()->user()->stores()->whereKey($this->storeId)->first() : null,
        ]);
    }
};
?>

<div class="dashboard-content">
    <div class="mb-4">
        <h1 class="h4 fw-bold text-dark mb-1">Payment Setup</h1>
        <p class="text-muted small mb-0">Add and confirm the bank account where your store's settlements should be sent.</p>
    </div>

    @if($notice)
        <div class="alert alert-{{ $noticeType === 'success' ? 'success' : ($noticeType === 'warning' ? 'warning' : 'danger') }} border-0 shadow-sm" role="status">{{ $notice }}</div>
    @endif

    @if($stores->isEmpty())
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <h2 class="h5 fw-bold">No store is linked to this account</h2>
            <p class="text-muted mb-3">Submit a store application before configuring settlement details.</p>
            <a class="btn btn-dark" href="{{ route('stores.register') }}" wire:navigate>Apply for a store</a>
        </div></div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                @if($stores->count() > 1)
                    <div class="mb-4">
                        <label for="settlement-store" class="form-label fw-semibold text-dark small mb-2">Store</label>
                        <select id="settlement-store" wire:model.live="storeId">
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($currentStore)
                    @php
                        $statusLabels = ['not_started' => 'Not started', 'pending' => 'Pending', 'processing' => 'Processing', 'ready' => 'Ready', 'failed' => 'Failed', 'requires_review' => 'Requires review'];
                        $statusStyles = ['ready' => 'success', 'failed' => 'danger', 'requires_review' => 'warning', 'processing' => 'info'];
                    @endphp
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 align-items-sm-center mb-4">
                        <div><h2 class="h5 fw-bold mb-1">{{ $currentStore->name }}</h2><p class="text-muted small mb-0">Payment setup status</p></div>
                        <span class="badge text-bg-{{ $statusStyles[$currentStore->payment_setup_status] ?? 'secondary' }} px-3 py-2">{{ $statusLabels[$currentStore->payment_setup_status] ?? 'Unknown' }}</span>
                    </div>

                    @if($currentStore->paystack_subaccount_code)
                        <div class="alert alert-light border mb-0">
                            <div class="fw-semibold">Paystack subaccount created</div>
                            <div class="small text-muted">Reference: {{ $currentStore->paystack_subaccount_code }}</div>
                            @if($currentStore->payment_setup_status !== 'ready')
                                <p class="small mb-0 mt-2">The subaccount is retained to prevent duplicates. Contact Blessed Stores Support so our team can verify and review it in the Paystack Dashboard. Product listings will be available after review.</p>
                                <button class="btn btn-outline-dark mt-3" type="button" wire:click="refreshSubaccountStatus" wire:loading.attr="disabled" wire:target="refreshSubaccountStatus">
                                    <span wire:loading.remove wire:target="refreshSubaccountStatus">Check Paystack verification status</span>
                                    <span wire:loading wire:target="refreshSubaccountStatus">Checking status...</span>
                                </button>
                            @endif
                            @error('account') <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div> @enderror
                        </div>
                    @elseif($currentStore->payment_setup_status === 'processing')
                        <div class="alert alert-info mb-0">A settlement request is being processed. Refresh this page shortly; do not submit another request.</div>
                    @elseif($currentStore->payment_setup_status === 'requires_review')
                        <div class="alert alert-warning mb-0">The previous result needs review. Contact Blessed Stores Support so our team can verify and review the subaccount in the Paystack Dashboard. Please do not retry.</div>
                    @else
                        @if($errors->has('banks'))
                            <div class="alert alert-warning d-flex flex-column flex-sm-row justify-content-between gap-2 align-items-sm-center">
                                <span>{{ $errors->first('banks') }}</span>
                                <button class="btn btn-sm btn-outline-dark" type="button" wire:click="retryBankList" wire:loading.attr="disabled" wire:target="retryBankList">Retry</button>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="settlement-bank" class="form-label fw-semibold text-dark small mb-2">Bank</label>
                                <select id="settlement-bank" wire:model.live="bankCode" @disabled($banks === [])>
                                    <option value="">Select a bank</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('bankCode') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="settlement-account" class="form-label fw-semibold text-dark small mb-2">Account number</label>
                                <input id="settlement-account" type="text" inputmode="numeric" autocomplete="off" maxlength="10" wire:model.live.debounce.300ms="accountNumber" placeholder="10-digit account number">
                                @error('accountNumber') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        @if($accountName)
                            <div class="alert alert-success mt-4 mb-0"><div class="fw-semibold">Resolved account holder</div><div>{{ $accountName }}</div></div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="account-name-confirmed" wire:model="accountNameConfirmed">
                                <label class="form-check-label" for="account-name-confirmed">I confirm this name matches the intended settlement account.</label>
                                @error('accountNameConfirmed') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            @if(!$accountNameConfirmed)
                                <button class="btn btn-outline-dark mt-3" type="button" wire:click="confirmAccountName" wire:loading.attr="disabled" wire:target="confirmAccountName">
                                    <span wire:loading.remove wire:target="confirmAccountName">Confirm account name</span><span wire:loading wire:target="confirmAccountName">Confirming...</span>
                                </button>
                            @else
                                <button class="btn btn-dark mt-3" type="button" wire:click="createSubaccount" wire:loading.attr="disabled" wire:target="createSubaccount">
                                    <span wire:loading.remove wire:target="createSubaccount">Create settlement account</span><span wire:loading wire:target="createSubaccount">Creating account...</span>
                                </button>
                            @endif
                        @else
                            <button class="btn btn-dark mt-4" type="button" wire:click="resolveAccount" wire:loading.attr="disabled" wire:target="resolveAccount" @disabled($banks === [])>
                                <span wire:loading.remove wire:target="resolveAccount">Resolve account</span><span wire:loading wire:target="resolveAccount">Resolving...</span>
                            </button>
                        @endif

                        @error('account') <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div> @enderror
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>