<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;
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
            'invoice_url' => 'https://invoice.mock.test/'.Str::random(32),
        ];
    }

    public function verifyWebhookSignature(array $payload, ?Request $request = null): bool
    {
        return isset($payload['external_id']);
    }
}
