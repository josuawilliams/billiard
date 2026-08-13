<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGateway
{
    public function getName(): string
    {
        return 'midtrans';
    }

    public function createTransaction(Payment $payment): array
    {
        $booking = $payment->booking;
        $servers = config('midtrans.is_production', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $serverKey = config('midtrans.server_key');

        $response = Http::withBasicAuth($serverKey, '')
            ->post($servers, [
                'transaction_details' => [
                    'order_id' => $payment->transaction_id,
                    'gross_amount' => (int) round((float) $booking->total_price),
                ],
                'customer_details' => [
                    'first_name' => $booking->user->name,
                    'email' => $booking->user->email,
                ],
            ]);

        $response->throw();

        return [
            'snap_token' => $response->json('token'),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $serverKey = config('midtrans.server_key');

        if (! $signature || ! $orderId || ! $statusCode || ! $grossAmount || ! $serverKey) {
            return false;
        }

        return hash_equals(
            hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey),
            $signature
        );
    }
}