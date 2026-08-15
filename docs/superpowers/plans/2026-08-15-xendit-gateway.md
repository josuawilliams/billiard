# Xendit Gateway (Invoice API) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Midtrans with the Xendit Invoice API while keeping the Mock gateway, changing the payment response field from `snap_token` to `invoice_url`.

**Architecture:** Add `XenditGateway` implementing the existing `PaymentGateway` interface; delete `MidtransGateway`; update `MockGateway`, `PaymentService`, config, controller webhook, and docs. Webhook verification uses Xendit's `X-Callback-Token` header (passed into the signature method via a new optional `Request` param).

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit via `php artisan test`, Laravel HTTP client (`Http::fake()` for tests).

## Global Constraints

- All responses use `App\Support\ApiResponse::success()` / `ApiResponse::error()`.
- Success shape: `{"success": true, "message": "...", "data": ...}`; error shape: `{"success": false, "message": "..."}`.
- `PaymentGateway` interface methods stay named: `getName(): string`, `createTransaction(Payment $payment): array`, `verifyWebhookSignature(array $payload, ?Request $request = null): bool`.
- Xendit Invoice API: `POST https://api.xendit.co/v2/invoices`, Basic Auth with secret API key. Environment is determined by the key prefix (`xnd_development_` / `xnd_production_`) — no `is_production` flag.
- Webhook payload: `{ id, external_id, status, amount, ... }`; header `X-Callback-Token`. Status `PAID` → success, anything else → failed.
- Payment response field is `invoice_url` (was `snap_token`).
- `.env` / `.env.example`: replace `MIDTRANS_*` with `XENDIT_API_KEY` and `XENDIT_CALLBACK_TOKEN`.
- Tests run with `php artisan test`.

---

### Task 1: Config + env for Xendit

**Files:**
- Modify: `config/payment.php`
- Modify: `config/services.php`
- Modify: `.env`
- Modify: `.env.example`

**Interfaces:**
- Produces: `config('payment.xendit.api_key')` → `XENDIT_API_KEY`, `config('payment.xendit.callback_token')` → `XENDIT_CALLBACK_TOKEN`. `config('services.midtrans.*')` no longer exists.

- [ ] **Step 1: Add xendit block to `config/payment.php`**

Replace the entire file content of `config/payment.php`:

```php
<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'mock' => [
        'server_key' => env('PAYMENT_MOCK_SERVER_KEY', 'mock-server-key'),
    ],

    'xendit' => [
        'api_key' => env('XENDIT_API_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],
];
```

- [ ] **Step 2: Remove `midtrans` from `config/services.php`**

In `config/services.php`, delete the `'midtrans' => [...]` block (lines 38-42). The file should end after the `slack` block:

```php
<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
```

- [ ] **Step 3: Update `.env` and `.env.example`**

In both `.env` and `.env.example`, replace the payment-related lines:

```env
PAYMENT_GATEWAY=xendit
PAYMENT_MOCK_SERVER_KEY=mock-server-key
XENDIT_API_KEY=
XENDIT_CALLBACK_TOKEN=
```

Remove these lines if present: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`.

- [ ] **Step 4: Verify config**

Run:
```bash
php artisan config:clear
php artisan tinker --execute="var_dump(config('payment.gateway')); var_dump(config('payment.xendit.api_key')); var_dump(config('payment.xendit.callback_token')); var_dump(config('services.midtrans'));"
```

Expected:
```
string(6) "xendit"
NULL
NULL
NULL
```

- [ ] **Step 5: Commit**

```bash
git add config/payment.php config/services.php .env .env.example
git commit -m "feat: add xendit payment config"
```

---

### Task 2: Update PaymentGateway interface + MockGateway

**Files:**
- Modify: `app/Services/Gateways/PaymentGateway.php`
- Modify: `app/Services/Gateways/MockGateway.php`
- Create: `tests/Feature/PaymentGatewayMockTest.php`

**Interfaces:**
- Consumes: `config('payment.mock.server_key')`.
- Produces: interface `verifyWebhookSignature(array $payload, ?Request $request = null): bool`. `MockGateway::createTransaction` returns `['invoice_url' => string]`.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/PaymentGatewayMockTest.php`:

```php
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
            'signature_key' => 'abc',
            'order_id' => 'INV-1-ABC',
            'status_code' => '200',
            'gross_amount' => '10000',
        ];

        $this->assertTrue((new MockGateway())->verifyWebhookSignature($payload, new Request()));
    }

    public function test_payment_service_resolves_xendit_when_configured(): void
    {
        config(['payment.gateway' => 'xendit']);

        $service = new PaymentService();

        $this->assertInstanceOf(\App\Services\Gateways\XenditGateway::class, $service->resolveGateway());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PaymentGatewayMockTest`
Expected: FAIL — `MockGateway::createTransaction` still returns `snap_token`; `PaymentGateway` interface has no `Request` param; `XenditGateway` class does not exist.

- [ ] **Step 3: Update the interface**

Replace `app/Services/Gateways/PaymentGateway.php`:

```php
<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function getName(): string;

    public function createTransaction(Payment $payment): array;

    public function verifyWebhookSignature(array $payload, ?Request $request = null): bool;
}
```

- [ ] **Step 4: Update MockGateway**

Replace `app/Services/Gateways/MockGateway.php`:

```php
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
```

- [ ] **Step 5: Run tests to verify pass (except Xendit resolution)**

Run: `php artisan test --filter=PaymentGatewayMockTest`
Expected: `test_mock_gateway_returns_invoice_url` and `test_mock_gateway_webhook_signature_with_request` PASS; `test_payment_service_resolves_xendit_when_configured` FAIL (XenditGateway does not exist yet). This is expected — it will pass after Task 3.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gateways/PaymentGateway.php app/Services/Gateways/MockGateway.php tests/Feature/PaymentGatewayMockTest.php
git commit -m "feat: update PaymentGateway interface and MockGateway to invoice_url"
```

---

### Task 3: XenditGateway

**Files:**
- Create: `app/Services/Gateways/XenditGateway.php`
- Create: `tests/Feature/XenditGatewayTest.php`

**Interfaces:**
- Consumes: `config('payment.xendit.api_key')`, `config('payment.xendit.callback_token')`, `App\Models\Payment` (with `booking` relation), `Illuminate\Http\Request`.
- Produces: `XenditGateway::createTransaction(Payment $payment): array` returning `['invoice_url' => string]`; `XenditGateway::verifyWebhookSignature(array $payload, ?Request $request = null): bool`; `getName(): string` returning `'xendit'`.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/XenditGatewayTest.php`:

```php
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

        $this->assertTrue((new XenditGateway())->verifyWebhookSignature([], $request));
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=XenditGatewayTest`
Expected: FAIL — class `XenditGateway` not found.

- [ ] **Step 3: Create XenditGateway**

Create `app/Services/Gateways/XenditGateway.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test --filter=XenditGatewayTest`
Expected: all 6 tests PASS.

- [ ] **Step 5: Run the mock+resolve test again**

Run: `php artisan test --filter=PaymentGatewayMockTest`
Expected: all 3 tests PASS (including Xendit resolution).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gateways/XenditGateway.php tests/Feature/XenditGatewayTest.php
git commit -m "feat: add Xendit invoice gateway"
```

---

### Task 4: PaymentService resolution + invoice_url

**Files:**
- Modify: `app/Services/PaymentService.php`
- Create: `tests/Feature/PaymentServiceTest.php`

**Interfaces:**
- Consumes: `config('payment.gateway')`, `XenditGateway`, `MockGateway`.
- Produces: `PaymentService::createPayment(Booking $booking): array` returning `['payment_id','transaction_id','amount','status','invoice_url','payment_gateway']`; `PaymentService::verifyWebhook(array $payload, ?Request $request = null): bool`.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/PaymentServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PaymentServiceTest`
Expected: FAIL — `createPayment` still returns `snap_token`, no `invoice_url`.

- [ ] **Step 3: Update PaymentService**

Replace `app/Services/PaymentService.php`:

```php
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\MockGateway;
use App\Services\Gateways\PaymentGateway;
use App\Services\Gateways\XenditGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function resolveGateway(): PaymentGateway
    {
        return match (config('payment.gateway', 'mock')) {
            'xendit' => new XenditGateway(),
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
                    'invoice_url' => $gatewayResult['invoice_url'],
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
                'invoice_url' => $gatewayResult['invoice_url'],
                'payment_gateway' => $gateway->getName(),
            ];
        });
    }

    public function verifyWebhook(array $payload, ?Request $request = null): bool
    {
        return $this->resolveGateway()->verifyWebhookSignature($payload, $request);
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test --filter=PaymentServiceTest`
Expected: all 2 tests PASS.

- [ ] **Step 5: Run full suite**

Run: `php artisan test`
Expected: all previous + new tests PASS (6 original + new). 

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/PaymentServiceTest.php
git commit -m "feat: PaymentService returns invoice_url and resolves Xendit"
```

---

### Task 5: PaymentController webhook → Xendit

**Files:**
- Modify: `app/Http/Controllers/PaymentController.php`
- Create: `tests/Feature/PaymentWebhookTest.php`

**Interfaces:**
- Consumes: `PaymentService::verifyWebhook(array $payload, ?Request $request = null): bool`.
- Produces: `POST /api/payment/webhook` accepting `{ external_id, status, amount }` + header `X-Callback-Token`; `PAID` → payment `success` + booking `paid`; else payment `failed`; idempotent.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/PaymentWebhookTest.php`:

```php
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
        $this->makePayment();

        $response = $this->post('/api/payment/webhook', [
            'external_id' => 'UNKNOWN',
            'status' => 'PAID',
            'amount' => 100000,
        ], ['Accept' => 'application/json']);

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid signature']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PaymentWebhookTest`
Expected: FAIL — controller still uses `order_id`, `transaction_status`, and `verifyWebhook($payload)` without request.

- [ ] **Step 3: Update PaymentController**

Replace `app/Http/Controllers/PaymentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function create(CreatePaymentRequest $request)
    {
        $booking = $request->user()->bookings()->findOrFail($request->booking_id);

        if ($booking->status !== 'pending') {
            return ApiResponse::error('Booking is not payable', 422);
        }

        if ($booking->payment()->where('status', 'success')->exists()) {
            return ApiResponse::error('Booking already paid', 422);
        }

        $result = $this->paymentService->createPayment($booking);

        return ApiResponse::success($result, 'Payment created. Redirect customer to Xendit invoice.', 201);
    }

    public function status(Request $request, Payment $payment)
    {
        if ($payment->booking->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success($payment->load('booking'), 'Payment status retrieved');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (! $this->paymentService->verifyWebhook($payload, $request)) {
            return ApiResponse::error('Invalid signature', 401);
        }

        $payment = Payment::where('transaction_id', $payload['external_id'])->first();

        if (! $payment) {
            return ApiResponse::error('Payment not found', 404);
        }

        if ($payment->status === 'success') {
            return ApiResponse::success($payment, 'Payment already processed');
        }

        $isSuccess = ($payload['status'] ?? null) === 'PAID';

        DB::transaction(function () use ($payment, $isSuccess) {
            if ($isSuccess) {
                $payment->update(['status' => 'success', 'paid_at' => now()]);
                $payment->booking()->update(['status' => 'paid']);
            } else {
                $payment->update(['status' => 'failed']);
            }
        });

        return ApiResponse::success($payment->fresh(), 'Webhook processed');
    }

    public function adminPayments(Request $request)
    {
        $payments = Payment::with('booking.user', 'booking.table')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success($payments, 'Payments retrieved');
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test --filter=PaymentWebhookTest`
Expected: all 4 tests PASS.

- [ ] **Step 5: Run full suite**

Run: `php artisan test`
Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentController.php tests/Feature/PaymentWebhookTest.php
git commit -m "feat: Xendit webhook handling in PaymentController"
```

---

### Task 6: Delete MidtransGateway

**Files:**
- Delete: `app/Services/Gateways/MidtransGateway.php`

**Interfaces:**
- Consumes: nothing (file is dead code after Tasks 3-4).

- [ ] **Step 1: Delete the file**

Run:
```bash
rm app/Services/Gateways/MidtransGateway.php
```

- [ ] **Step 2: Verify no references remain**

Run: `rg -n "MidtransGateway|midtrans" app/ config/ routes/ tests/`
Expected: no matches.

- [ ] **Step 3: Run full test suite**

Run: `php artisan test`
Expected: all tests PASS.

- [ ] **Step 4: Commit**

```bash
git add -A app/Services/Gateways/MidtransGateway.php
git commit -m "refactor: remove Midtrans gateway"
```

---

### Task 7: Update API.md documentation

**Files:**
- Modify: `API.md`

**Interfaces:**
- Consumes: the new behavior from Tasks 1-5: `invoice_url`, Xendit webhook payload `external_id`/`status`/`PAID`, header `X-Callback-Token`.

- [ ] **Step 1: Update the payment/create intro + response**

In `API.md`, replace lines ~735 and the 201 response example. The paragraph becomes:

```
Membuat pembayaran untuk booking `pending` milik user. Gateway aktif diambil dari `PAYMENT_GATEWAY` (`mock` default / `xendit`). `transaction_id` dibuat otomatis berformat `INV-<booking_id>-<6 karakter acak>`. Untuk gateway `mock`, `invoice_url` berformat `https://invoice.mock.test/<32 karakter acak>`; untuk `xendit`, diambil dari API Xendit.
```

Replace the response example with:

```json
{
  "success": true,
  "message": "Payment created. Redirect customer to Xendit invoice.",
  "data": {
    "payment_id": 2,
    "transaction_id": "INV-5-SOI9K3",
    "amount": "120000.00",
    "status": "pending",
    "invoice_url": "https://invoice.mock.test/abCdEfGhIjKlMnOpQrStUvWxYz123456",
    "payment_gateway": "mock"
  }
}
```

- [ ] **Step 2: Update the webhook section**

Replace the webhook request/response documentation in `API.md` (section `### POST /api/payment/webhook`):

New request field table:

| Field | Tipe | Keterangan |
|---|---|---|
| `external_id` | string | `transaction_id` payment (mis. `INV-5-SOI9K3`) |
| `status` | string | `PAID` (sukses) atau lainnya (`EXPIRED`, `PENDING`, ...) |
| `amount` | number | Jumlah pembayaran |

New header table:

| Header | Keterangan |
|---|---|
| `X-Callback-Token` | Token verifikasi webhook dari dashboard Xendit; dibandingkan dengan `config('payment.xendit.callback_token')` memakai `hash_equals` |

Replace the SHA-512 signature explanation block (lines ~860-868) with:

```
Verifikasi: header `X-Callback-Token` pada request dibandingkan (dengan `hash_equals`) terhadap `XENDIT_CALLBACK_TOKEN` di `.env`. Jika tidak cocok → `401 Invalid signature`.
```

Replace the webhook request curl example (lines ~870-883) with:

```bash
curl -X POST http://localhost:8000/api/payment/webhook \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Callback-Token: <token>" \
  -d '{
    "external_id": "INV-6-NDRZEM",
    "status": "PAID",
    "amount": 75000
  }'
```

Update the two failure-example responses (lines ~927-945) so `payment_gateway` is `"xendit"` and the status is `"failed"`. Update the 404 error example caption (line ~956) to `external_id`.

- [ ] **Step 3: Update `payment_gateway` occurrences elsewhere in API.md**

Run: `rg -n '"payment_gateway": "mock"' API.md`
For any remaining occurrences in booking/payment status examples, leave `"mock"` as-is (it's a sample from the mock gateway) unless the surrounding section explicitly describes Xendit.

- [ ] **Step 4: Run full test suite**

Run: `php artisan test`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add API.md
git commit -m "docs: update API.md for Xendit gateway"
```

---

### Task 8: Manual verification (Postman)

**Files:** none (verification only).

- [ ] **Step 1: Ensure config is clean and server running**

Run:
```bash
php artisan config:clear
php artisan serve
```

- [ ] **Step 2: Verify mock flow returns invoice_url**

With `PAYMENT_GATEWAY=mock` in `.env`:
1. `POST /api/login` (user@example.com / password) → capture token.
2. `GET /api/tables` with Bearer token → 200 list.
3. `POST /api/bookings` `{"table_id":1,"booking_date":"2026-08-20","start_time":"14:00","duration":2}` → 201, note `id`.
4. `POST /api/payment/create` `{"booking_id":<id>}` → 201 with `"invoice_url": "https://invoice.mock.test/..."`.
5. Simulate webhook:
   ```bash
   curl -X POST http://localhost:8000/api/payment/webhook \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"external_id":"<transaction_id>","status":"PAID","amount":<amount>}'
   ```
   → 200, payment `success`, booking `paid`.

- [ ] **Step 3: Verify real Xendit flow**

After registering Xendit and obtaining the sandbox API key, set in `.env`:
```
PAYMENT_GATEWAY=xendit
XENDIT_API_KEY=xnd_development_<...>
XENDIT_CALLBACK_TOKEN=<from Dashboard → Settings → Developers → Webhook Settings>
```
Then `php artisan config:clear`, repeat Steps 2.1-2.4. `invoice_url` should be a real `https://invoice.xendit.co/web/invoices/...` URL.

- [ ] **Step 4: Final full test suite**

Run: `php artisan test`
Expected: all tests PASS.
