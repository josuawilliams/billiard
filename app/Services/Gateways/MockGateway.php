<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Support\Str;

class MockGateway implements PaymentGateway
{
    public function getName(): string
    {
        return 'mock';
    }

    public function createTransaction(Payment $payment): array
    {
        return [
            'snap_token' => 'mock-'.Str::random(32),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;

        if (! $signature || ! $orderId || ! $statusCode || ! $grossAmount) {
            return false;
        }

        return hash_equals(
            hash('sha512', $orderId.$statusCode.$grossAmount.config('payment.mock.server_key')),
            $signature
        );
    }
}