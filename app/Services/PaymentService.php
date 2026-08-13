<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\MidtransGateway;
use App\Services\Gateways\MockGateway;
use App\Services\Gateways\PaymentGateway;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($booking, $gateway) {
            $existing = $booking->payment()
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if ($existing) {
                $gatewayResult = $gateway->createTransaction($existing);

                return [
                    'payment_id' => $existing->id,
                    'transaction_id' => $existing->transaction_id,
                    'amount' => $existing->amount,
                    'status' => $existing->status,
                    'snap_token' => $gatewayResult['snap_token'],
                    'payment_gateway' => $gateway->getName(),
                ];
            }

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
        });
    }

    public function verifyWebhook(array $payload): bool
    {
        return $this->resolveGateway()->verifyWebhookSignature($payload);
    }
}
