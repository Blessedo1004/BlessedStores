<?php
// app/Services/PaystackService.php
namespace App\Services;

use GuzzleHttp\Client;

class PaystackService
{
    protected $client;
    protected $secretKey;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.paystack.co',
            'headers' => [
                'Authorization' => 'Bearer '.config('services.paystack.secret_key'),
                'Content-Type' => 'application/json',
            ],
            'verify' => false // ← Add this line to disable SSL verification
        ]);
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function initializeTransaction(array $data)
    {
        $response = $this->client->post('/transaction/initialize', [
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }

    public function verifyTransaction($reference)
    {
        $response = $this->client->get("/transaction/verify/{$reference}");

        return json_decode($response->getBody(), true);
    }
}