<?php

namespace App\Services\Gateways;

use App\Models\Payment;

interface PaymentGateway
{
    public function getName(): string;

    public function createTransaction(Payment $payment): array;

    public function verifyWebhookSignature(array $payload): bool;
}