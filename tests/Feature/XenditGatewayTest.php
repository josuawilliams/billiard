<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\XenditGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transaction_returns_invoice_url(): void
    {
        Http::fake([
            'api.xendit.co/*' => Http::response([
                'id' => 'inv_123',
                'external_id' => 'INV-1-ABC123',
                'status' => 'PENDING',
                'invoice_url' => 'https://invoice.xendit.co/web/invoices/inv_123',
            ], 201),
        ]);

        config(['payment.xendit.api_key' => 'xnd_development_test']);

        $booking = Booking::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'transaction_id' => 'INV-1-ABC123',
        ]);

        $result = (new XenditGateway())->createTransaction($payment);

        $this->assertSame('https://invoice.xendit.co/web/invoices/inv_123', $result['invoice_url']);
        Http::assertSent(function ($request) use ($payment) {
            $body = $request->data();
            return $request->url() === 'https://api.xendit.co/v2/invoices'
                && $body['external_id'] === $payment->transaction_id
                && $body['amount'] === (int) round((float) $payment->booking->total_price)
                && $body['currency'] === 'IDR'
                && $body['customer']['email'] === $payment->booking->user->email;
        });
    }

    public function test_create_transaction_throws_on_error(): void
    {
        Http::fake([
            'api.xendit.co/*' => Http::response(['error_code' => 'API_VALIDATION_ERROR'], 400),
        ]);

        config(['payment.xendit.api_key' => 'xnd_development_test']);

        $booking = Booking::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->create(['booking_id' => $booking->id]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new XenditGateway())->createTransaction($payment);
    }

    public function test_verify_webhook_with_valid_token(): void
    {
        config(['payment.xendit.callback_token' => 'secret-token']);

        $request = Request::create('/api/payment/webhook', 'POST', [
            'external_id' => 'INV-1-ABC123',
            'status' => 'PAID',
        ]);
        $request->headers->set('X-Callback-Token', 'secret-token');

        $payload = $request->all();

        $this->assertTrue((new XenditGateway())->verifyWebhookSignature($payload, $request));
    }

    public function test_verify_webhook_rejects_wrong_token(): void
    {
        config(['payment.xendit.callback_token' => 'secret-token']);

        $request = Request::create('/api/payment/webhook', 'POST', [
            'external_id' => 'INV-1-ABC123',
            'status' => 'PAID',
        ]);
        $request->headers->set('X-Callback-Token', 'wrong-token');

        $this->assertFalse((new XenditGateway())->verifyWebhookSignature([], $request));
    }

    public function test_verify_webhook_rejects_missing_external_id(): void
    {
        config(['payment.xendit.callback_token' => 'secret-token']);

        $request = Request::create('/api/payment/webhook', 'POST', []);
        $request->headers->set('X-Callback-Token', 'secret-token');

        $this->assertFalse((new XenditGateway())->verifyWebhookSignature([], $request));
    }

    public function test_get_name(): void
    {
        $this->assertSame('xendit', (new XenditGateway())->getName());
    }
}
