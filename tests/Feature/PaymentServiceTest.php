<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_returns_invoice_url(): void
    {
        config(['payment.gateway' => 'mock']);

        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $result = (new PaymentService())->createPayment($booking);

        $this->assertArrayHasKey('invoice_url', $result);
        $this->assertStringStartsWith('https://invoice.mock.test/', $result['invoice_url']);
        $this->assertSame('mock', $result['payment_gateway']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_create_payment_reuses_existing_pending_payment(): void
    {
        config(['payment.gateway' => 'mock']);

        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $existing = Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'pending',
            'transaction_id' => 'INV-9-EXIST',
        ]);

        $result = (new PaymentService())->createPayment($booking);

        $this->assertSame($existing->id, $result['payment_id']);
        $this->assertSame('INV-9-EXIST', $result['transaction_id']);
    }
}
