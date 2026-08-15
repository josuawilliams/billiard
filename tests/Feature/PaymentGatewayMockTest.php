<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\MockGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentGatewayMockTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_gateway_returns_invoice_url(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->create(['booking_id' => $booking->id]);

        $result = (new MockGateway())->createTransaction($payment);

        $this->assertArrayHasKey('invoice_url', $result);
        $this->assertStringStartsWith('https://invoice.mock.test/', $result['invoice_url']);
    }

    public function test_mock_gateway_webhook_signature_with_request(): void
    {
        $payload = [
            'external_id' => 'INV-1-ABC',
            'status' => 'PAID',
            'amount' => '10000',
        ];

        $this->assertTrue((new MockGateway())->verifyWebhookSignature($payload, new Request()));
    }

    public function test_mock_gateway_webhook_signature_rejects_without_external_id(): void
    {
        $this->assertFalse((new MockGateway())->verifyWebhookSignature([], new Request()));
    }

    public function test_payment_service_resolves_xendit_when_configured(): void
    {
        config(['payment.gateway' => 'xendit']);

        $service = new PaymentService();

        $this->assertInstanceOf(\App\Services\Gateways\XenditGateway::class, $service->resolveGateway());
    }
}
