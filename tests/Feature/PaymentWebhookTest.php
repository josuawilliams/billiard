<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(string $status = 'pending'): Payment
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        return Payment::factory()->create([
            'booking_id' => $booking->id,
            'transaction_id' => 'INV-1-WBHOOK',
            'status' => $status,
            'payment_gateway' => 'xendit',
        ]);
    }

    public function test_webhook_paid_marks_payment_and_booking_success(): void
    {
        config(['payment.gateway' => 'mock']);
        $payment = $this->makePayment();

        $response = $this->post('/api/payment/webhook', [
            'external_id' => 'INV-1-WBHOOK',
            'status' => 'PAID',
            'amount' => 100000,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'success']);
        $this->assertDatabaseHas('bookings', ['id' => $payment->booking_id, 'status' => 'paid']);
    }

    public function test_webhook_paid_is_idempotent(): void
    {
        config(['payment.gateway' => 'mock']);
        $payment = $this->makePayment('success');

        $response = $this->post('/api/payment/webhook', [
            'external_id' => 'INV-1-WBHOOK',
            'status' => 'PAID',
            'amount' => 100000,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJson(['message' => 'Payment already processed']);
    }

    public function test_webhook_non_paid_marks_failed(): void
    {
        config(['payment.gateway' => 'mock']);
        $payment = $this->makePayment();

        $response = $this->post('/api/payment/webhook', [
            'external_id' => 'INV-1-WBHOOK',
            'status' => 'EXPIRED',
            'amount' => 100000,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    }

    public function test_webhook_invalid_signature_returns_401(): void
    {
        config(['payment.gateway' => 'xendit']);
        config(['payment.xendit.callback_token' => 'secret-token']);
        $this->makePayment();

        $response = $this->post('/api/payment/webhook', [
            'external_id' => 'INV-1-WBHOOK',
            'status' => 'PAID',
            'amount' => 100000,
        ], ['Accept' => 'application/json']);

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid signature']);
    }
}
