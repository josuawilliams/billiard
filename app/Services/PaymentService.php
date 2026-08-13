<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\MidtransGateway;
use App\Services\Gateways\MockGateway;
use App\Services\Gateways\PaymentGateway;
use Illuminate\Support\Str;

class PaymentService
{
    public function resolveGateway(): PaymentGateway
    {
        return match (config('payment.gateway', 'mock')) {
            'midtrans' => new MidtransGateway(),
            default => new MockGateway(),
        };
    }

    public function createPayment(Booking $booking): array
    {
        $gateway = $this->resolveGateway();

        $transactionId = 'INV-'.$booking->id.'-'.Str::upper(Str::random(6));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_gateway' => $gateway->getName(),
            'transaction_id' => $transactionId,
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        $gatewayResult = $gateway->createTransaction($payment);

        return [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'snap_token' => $gatewayResult['snap_token'],
            'payment_gateway' => $gateway->getName(),
        ];
    }

    public function verifyWebhook(array $payload): bool
    {
        return $this->resolveGateway()->verifyWebhookSignature($payload);
    }
}