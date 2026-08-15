<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function getName(): string;

    public function createTransaction(Payment $payment): array;

    public function verifyWebhookSignature(array $payload, ?Request $request = null): bool;
}
