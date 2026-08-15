<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class XenditGateway implements PaymentGateway
{
    public function getName(): string
    {
        return 'xendit';
    }

    public function createTransaction(Payment $payment): array
    {
        $booking = $payment->booking;

        $response = Http::withBasicAuth(config('payment.xendit.api_key'), '')
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $payment->transaction_id,
                'description' => 'Booking Billiard #'.$booking->id,
                'amount' => (int) round((float) $booking->total_price),
                'currency' => 'IDR',
                'payer_email' => $booking->user->email,
                'customer' => [
                    'given_names' => $booking->user->name,
                    'email' => $booking->user->email,
                ],
                'invoice_duration' => 900,
            ]);

        $response->throw();

        return [
            'invoice_url' => $response->json('invoice_url'),
        ];
    }

    public function verifyWebhookSignature(array $payload, ?Request $request = null): bool
    {
        $expectedToken = config('payment.xendit.callback_token');
        $incomingToken = $request?->header('X-Callback-Token');
        $externalId = $payload['external_id'] ?? null;

        if (! $expectedToken || ! $incomingToken || ! $externalId) {
            return false;
        }

        return hash_equals($expectedToken, $incomingToken);
    }
}
