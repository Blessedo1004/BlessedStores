<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaystackService;

class PaymentController extends Controller
{
    protected $paystack;

    public function __construct(PaystackService $paystack)
    {
        $this->paystack = $paystack;
    }

    public function showForm()
    {
        return view('payment.form');
    }

    public function initialize(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:100',
        ]);

        $data = [
            'email' => $request->email,
            'amount' => $request->amount * 100,
            'metadata' => [
                'name' => $request->name,
                'phone' => $request->phone
            ],
            'callback_url' => route('payment.callback'),
        ];

        $response = $this->paystack->initializeTransaction($data);
        return redirect($response['data']['authorization_url']);
    }

    public function callback(Request $request)
    {
        // TEMPORARY TESTING BLOCK - REMOVE LATER
        if (app()->environment('local') && !$request->has('reference')) {
            $mockResponse = [
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'TEST_'.rand(1000,9999),
                    'amount' => 10000,
                    'metadata' => $request->session()->get('payment_details', [
                        'name' => 'Test User',
                        'phone' => '07037848567'
                    ]),
                    'customer' => [
                        'email' => $request->session()->get('payment_details.email', 'test@example.com')
                    ]
                ]
            ];
            
            $paymentDetails = $mockResponse['data'];
            return view('payment.success', [
                'name' => $paymentDetails['metadata']['name'],
                'email' => $paymentDetails['customer']['email'],
                'phone' => $paymentDetails['metadata']['phone'],
                'amount' => $paymentDetails['amount'] / 100,
                'reference' => $paymentDetails['reference']
            ]);
        }
        // END TEMPORARY BLOCK

        $reference = $request->query('reference');
        $response = $this->paystack->verifyTransaction($reference);

        if ($response['status'] && $response['data']['status'] === 'success') {
            $paymentDetails = $response['data'];
            
            return view('payment.success', [
                'name' => $paymentDetails['metadata']['name'],
                'email' => $paymentDetails['customer']['email'],
                'phone' => $paymentDetails['metadata']['phone'],
                'amount' => $paymentDetails['amount'] / 100,
                'reference' => $paymentDetails['reference']
            ]);
        }

        return redirect()->route('payment.form')->with('error', 'Payment failed or was cancelled');
    }
}