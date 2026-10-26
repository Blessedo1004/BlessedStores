<?php

namespace App\Exceptions;

use RuntimeException;

class PaystackApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $providerMessage,
    ) {
        parent::__construct("Paystack API returned HTTP {$statusCode}.");
    }
}