# Xendit Gateway (Invoice API) Design

> **Status:** Approved by user
> **Date:** 2026-08-15

## Goal

Replace the Midtrans payment gateway with Xendit Invoice API. Existing Mock gateway is kept so the flow remains testable without real credentials.

## Background

The app currently has:
- `app/Services/Gateways/PaymentGateway.php` — interface: `getName()`, `createTransaction(Payment $payment): array`, `verifyWebhookSignature(array $payload): bool`
- `app/Services/Gateways/MidtransGateway.php` — Snap token based
- `app/Services/Gateways/MockGateway.php` — returns a fake `snap_token`
- `app/Services/PaymentService.php` — resolves gateway from `config('payment.gateway')` via `match` (mock default / midtrans)
- `config/payment.php` — `gateway`, `mock.server_key`
- `config/services.php` — `midtrans` key (server_key, client_key, is_production)
- `PaymentController@webhook` — verifies signature, then `capture`/`settlement` → success

Decision (user-approved):
- **Remove** Midtrans entirely.
- **Keep** Mock + add **Xendit**.
- Response field changes: **`snap_token` → `invoice_url`**.
- Webhook verification via **Xendit `X-Callback-Token`** header.

## Xendit Invoice API Facts (verified from official docs)

- Create invoice: `POST https://api.xendit.co/v2/invoices`, Basic Auth with secret key, JSON body.
- Key fields in request: `external_id`, `amount`, `description`, `currency` (IDR), `payer_email`, `customer.given_names`, `customer.email`, `invoice_duration` (seconds).
- Response contains: `id`, `external_id`, `status` (`PENDING`/`PAID`/`EXPIRED`), `invoice_url` (`https://invoice.xendit.co/web/invoices/...`).
- Environment (sandbox vs live) is determined by the API key prefix (`xnd_development_` / `xnd_production_`). **No `is_production` flag needed.**
- Webhook (invoice callback): POST with JSON body `{ id, external_id, user_id, status, amount, paid_amount, paid_at, payer_email, ... }`, and a header **`X-Callback-Token`** unique per account per environment.
- To verify a webhook comes from Xendit: compare `X-Callback-Token` header against the token found in Dashboard → Settings → Developers → Webhook Settings. Also validate `external_id` matches `transaction_id`.
- Duplicate webhooks can be delivered (retries) → keep idempotent handling.

## Architecture

Keep the existing gateway pattern unchanged:

- **Delete:** `app/Services/Gateways/MidtransGateway.php`
- **Create:** `app/Services/Gateways/XenditGateway.php` implementing `PaymentGateway`
- **Modify:**
  - `app/Services/Gateways/MockGateway.php` — return `invoice_url` instead of `snap_token`
  - `app/Services/PaymentService.php` — resolve `xendit`; return `invoice_url` instead of `snap_token`
  - `config/payment.php` — add `xendit` key
  - `config/services.php` — remove `midtrans` key
  - `.env`, `.env.example` — replace `MIDTRANS_*` with `XENDIT_*`
  - `API.md` — update endpoint docs & examples
- **Unchanged:** `PaymentGateway` interface, route `POST /api/payment/webhook`, `PaymentController` (webhook idempotency already present).

## Component Details

### XenditGateway

```php
class XenditGateway implements PaymentGateway
{
    public function getName(): string; // 'xendit'

    public function createTransaction(Payment $payment): array
    {
        // POST https://api.xendit.co/v2/invoices
        // Basic auth: config('payment.xendit.api_key')
        // body:
        //   external_id    = $payment->transaction_id
        //   description    = "Booking Billiard #{$booking->id}"
        //   amount         = (int) round((float) $booking->total_price)
        //   currency       = 'IDR'
        //   payer_email    = $booking->user->email
        //   customer       = { given_names: $booking->user->name, email: $booking->user->email }
        //   invoice_duration = 900  (15 menit, sinkron dengan auto-expire booking)
        // $response->throw();
        // return ['invoice_url' => $response->json('invoice_url')];
    }

    public function verifyWebhookSignature(array $payload): bool;
    // header X-Callback-Token === config('payment.xendit.callback_token')
    // and payload['external_id'] non-empty
}
```

**Interface change:** `verifyWebhookSignature(array $payload): bool` cannot see the HTTP header. To verify the `X-Callback-Token` header, change the interface signature to accept the request:

```php
public function verifyWebhookSignature(array $payload, ?Request $request = null): bool;
```

`MockGateway::verifyWebhookSignature` keeps the same behavior (returns true when required fields present). `PaymentController@webhook` passes `$request` through.

### PaymentService

- `resolveGateway()`: `match (config('payment.gateway', 'mock')) { 'xendit' => new XenditGateway(), default => new MockGateway() }`.
- `createPayment()` returns `'invoice_url' => $gatewayResult['invoice_url']` (both branches).

### config/payment.php

```php
'gateway' => env('PAYMENT_GATEWAY', 'mock'),

'mock' => [
    'server_key' => env('PAYMENT_MOCK_SERVER_KEY', 'mock-server-key'),
],

'xendit' => [
    'api_key' => env('XENDIT_API_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
],
```

Remove `midtrans` block from `config/services.php`.

### .env / .env.example

```
PAYMENT_GATEWAY=xendit
PAYMENT_MOCK_SERVER_KEY=mock-server-key
XENDIT_API_KEY=
XENDIT_CALLBACK_TOKEN=
```

Remove `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`.

### Webhook (PaymentController)

- Call `$this->paymentService->verifyWebhook($payload, $request)`.
- `PaymentService::verifyWebhook` passes the request to `verifyWebhookSignature`.
- On valid: find payment by `external_id` (currently `Payment::where('transaction_id', $payload['order_id'])` → change to `$payload['external_id']`).
- `status === 'PAID'` → payment `success`, booking `paid`. Else → payment `failed`.
- Idempotency (payment already `success`) stays.

## API Response Changes

`POST /api/payment/create` response `data`:

```json
{
  "payment_id": 1,
  "transaction_id": "INV-1-ABC123",
  "amount": 100000,
  "status": "pending",
  "invoice_url": "https://invoice.xendit.co/web/invoices/xxxx",
  "payment_gateway": "xendit"
}
```

`MockGateway` returns `"invoice_url": "https://invoice.mock.test/<random>"`.

## Error Handling

- `$response->throw()` surfaces Xendit HTTP errors (400 validation, 401/403 auth).
- No new exception classes needed; existing Laravel error handling returns JSON.

## Testing

PHPUnit with `Http::fake()`:

1. `XenditGateway::createTransaction` success → returns `invoice_url`; assert correct URL, auth header, and request body fields.
2. `XenditGateway::createTransaction` failure (e.g., 401) → throws.
3. `verifyWebhookSignature` with correct/incorrect `X-Callback-Token`.
4. `PaymentService` resolves `xendit` gateway and returns `invoice_url`.
5. Webhook PAID → payment success + booking paid; duplicate webhook idempotent.
6. Mock gateway returns `invoice_url`.

Run with `php artisan test`.

## Out of Scope

- Real Xendit registration/dashboard setup (guided manually after implementation).
- Frontend rendering of the invoice page.
- Refund/disbursement APIs.
