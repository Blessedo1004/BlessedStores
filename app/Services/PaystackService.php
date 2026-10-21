<?php
// app/Services/PaystackService.php
namespace App\Services;

use App\Exceptions\PaystackApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    private const TIMEOUT_SECONDS = 10;

    public function fetchBanks(string $country = 'nigeria'): array
    {
        return Cache::remember('paystack.banks.' . strtolower($country), now()->addHours(12), function () use ($country): array {
            $response = $this->request('GET', '/bank', ['country' => $country]);
            $banks = data_get($response, 'data');

            if (! is_array($banks)) {
                throw new RuntimeException('Paystack returned an invalid bank list.');
            }

            return collect($banks)
                ->filter(fn ($bank) => is_array($bank)
                    && is_string($bank['name'] ?? null)
                    && is_string($bank['code'] ?? null))
                ->map(fn ($bank) => ['name' => $bank['name'], 'code' => $bank['code']])
                ->values()
                ->all();
        });
    }

    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        $response = $this->request('GET', '/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);

        $accountName = data_get($response, 'data.account_name');
        $resolvedNumber = data_get($response, 'data.account_number');

        if (! is_string($accountName) || ! is_string($resolvedNumber)) {
            throw new RuntimeException('Paystack could not verify those account details.');
        }

        return ['account_name' => $accountName, 'account_number' => $resolvedNumber];
    }

    public function createSubaccount(array $data): array
    {
        $payload = [
            'business_name' => $data['business_name'],
            'settlement_bank' => $data['settlement_bank'],
            'account_number' => $data['account_number'],
        ];

        foreach (['description', 'primary_contact_name', 'primary_contact_email', 'primary_contact_phone'] as $field) {
            if (filled($data[$field] ?? null)) {
                $payload[$field] = $data[$field];
            }
        }

        $percentageCharge = config('services.paystack.subaccount_percentage_charge');
        if ($percentageCharge !== null && $percentageCharge !== '') {
            $payload['percentage_charge'] = (float) $percentageCharge;
        }

        return $this->request('POST', '/subaccount', $payload);
    }

    public function fetchSubaccount(string $subaccountCode): array
    {
        if (! preg_match('/^ACCT_[A-Za-z0-9]+$/', $subaccountCode)) {
            throw new RuntimeException('Invalid Paystack subaccount code.');
        }

        $response = $this->request('GET', '/subaccount/' . rawurlencode($subaccountCode));
        $subaccount = data_get($response, 'data');

        if (! is_array($subaccount) || data_get($subaccount, 'subaccount_code') !== $subaccountCode) {
            throw new RuntimeException('Paystack returned an invalid subaccount record.');
        }

        return $subaccount;
    }

    public function initializeTransaction(array $data): array
    {
        return $this->request('POST', '/transaction/initialize', $data);
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $secretKey = config('services.paystack.secret_key');
        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        $request = Http::baseUrl(rtrim(config('services.paystack.payment_url', 'https://api.paystack.co'), '/'))
            ->withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(3);

        $response = $method === 'GET'
            ? $request->get($path, $data)
            : $request->post($path, $data);

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || ! ($payload['status'] ?? false)) {
            $providerMessage = is_array($payload) && is_string($payload['message'] ?? null)
                ? $payload['message']
                : 'Paystack could not complete the request.';

            // Never pass an echoed full account number into the UI or application logs.
            $providerMessage = preg_replace('/\b\d{10}\b/', '[account number]', $providerMessage) ?? 'Paystack could not complete the request.';
            $providerMessage = mb_substr($providerMessage, 0, 240);

            throw new PaystackApiException($response->status(), $providerMessage);
        }

        return $payload;
    }

}