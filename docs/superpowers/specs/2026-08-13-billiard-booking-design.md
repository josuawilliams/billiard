# Billiard Booking System — Design

**Tanggal:** 2026-08-13
**Basis:** `AI.md`

## 1. Ringkasan

Backend API Billiard Booking System berbasis Laravel 12 yang memungkinkan user melihat tabel billiard, memesan berdasarkan tanggal & waktu, dan membayar online. Admin mengelola tabel dan memonitor booking & transaksi.

## 2. Pendekatan Arsitektur

**Clean Architecture (Controller → Service → Model):**
- Controller tipis, logika bisnis di `Services/`
- Validasi memakai Form Request
- Payment lewat interface `PaymentGateway` sehingga bisa di-mock di development
- Sesuai "Expected AI Behavior" di AI.md

## 3. Tech Stack

- Backend: Laravel 12 (PHP 8.2)
- Auth: Laravel Sanctum (Bearer token)
- Database: **MySQL**, db `billiard_dev`
- Payment: Midtrans (Snap API) + `MockGateway` untuk development
- Queue: Laravel Queue (database driver) untuk auto-expire
- Scheduler: Laravel Scheduler

## 4. Database Schema

Semua migration dijalankan di database MySQL `billiard_dev`.

### users (tambah dari default Laravel)
- `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps
- `role` — enum: `user`, `admin`, default `user`

### tables
- `id`
- `name` (string)
- `price_per_hour` (decimal 10,2)
- timestamps

### bookings
- `id`
- `user_id` (FK → users, cascade)
- `table_id` (FK → tables, cascade)
- `booking_date` (date)
- `start_time` (time)
- `end_time` (time)
- `total_price` (decimal 10,2)
- `status` (enum: `pending`, `paid`, `cancelled`, `expired`), default `pending`
- timestamps
- **Index komposit** pada `(table_id, booking_date)`

### payments
- `id`
- `booking_id` (FK → bookings, cascade)
- `payment_gateway` (string: `midtrans` / `mock`)
- `transaction_id` (string, **unique**)
- `amount` (decimal 10,2)
- `status` (enum: `pending`, `success`, `failed`), default `pending`
- `paid_at` (nullable timestamp)
- timestamps

### Relationships
- User hasMany Bookings
- Table hasMany Bookings
- Booking belongsTo User & Table, hasOne Payment

### Seeder
- `UserSeeder` / `DatabaseSeeder`:
  - 1 admin: `admin@example.com` / `password`, role `admin`
  - 1 user contoh: `user@example.com` / `password`, role `user`
  - 4 tabel billiard contoh

## 5. Anti Double-Booking

Dilakukan dalam transaction dengan row lock (`lockForUpdate`) pada tabel `bookings`:

```
WHERE table_id = ?
  AND booking_date = ?
  AND start_time < requested_end
  AND end_time > requested_start
  AND status != cancelled
```

Jika ada tabrakan → tolak dengan HTTP 409.

Perhitungan end time: `end_time = start_time + duration (jam)`.

## 6. Alur Booking & Payment

1. **Buat booking** `POST /api/bookings` → hitung `end_time` & `total_price`, cek overlap, simpan status `pending`.
2. **Buat payment** `POST /api/payment/create` → validasi booking milik user & masih `pending`; `PaymentService` pilih gateway (`mock` di dev, `midtrans` di prod) generate Snap token/redirect URL; simpan row payments `pending` + `transaction_id`; balas `snap_token`/`redirect_url`.
3. **Webhook** `POST /api/payment/webhook` → verifikasi signature; update `payments.status` → success/failed & `paid_at`; update `bookings.status` → paid. **Idempotent** — payment yang sudah success tidak diproses ulang.
4. **Auto-expire** `ExpireBookingJob` via scheduler → setiap menit, booking `pending` berusia >15 menit → `expired`. Webhook sukses yang datang setelah expire tetap bisa mengubah ke `paid`.

## 7. Folder Structure

```
app/
 ├── Models/
 │    ├── User.php
 │    ├── Table.php
 │    ├── Booking.php
 │    └── Payment.php
 ├── Http/Controllers/
 │    ├── AuthController.php
 │    ├── TableController.php
 │    ├── BookingController.php
 │    └── PaymentController.php
 ├── Http/Requests/
 │    ├── StoreBookingRequest.php
 │    ├── StoreTableRequest.php
 │    ├── UpdateTableRequest.php
 │    ├── LoginRequest.php
 │    └── RegisterRequest.php
 ├── Http/Middleware/
 │    └── AdminMiddleware.php
 ├── Services/
 │    ├── BookingService.php
 │    ├── PaymentService.php
 │    └── Gateways/
 │         ├── PaymentGateway.php        (interface)
 │         ├── MidtransGateway.php
 │         └── MockGateway.php
 └── Jobs/
      └── ExpireBookingJob.php
```

## 8. API Endpoints

Semua route di `routes/api.php`, prefix `/api`, response JSON.

### Public
| Method | Endpoint | Keterangan |
|--------|----------|-----------|
| POST | `/api/register` | Register user |
| POST | `/api/login` | Login → Sanctum token |

### Auth (Sanctum Bearer token, user & admin)
| Method | Endpoint | Keterangan |
|--------|----------|-----------|
| POST | `/api/logout` | Revoke token |
| GET | `/api/tables` | List tabel |
| POST | `/api/bookings` | Buat booking |
| GET | `/api/bookings` | List booking milik user |
| GET | `/api/bookings/{id}` | Detail booking (khusus pemilik) |
| POST | `/api/payment/create` | Generate payment utk booking |
| GET | `/api/payment/status/{id}` | Cek status payment |

### Admin (`role=admin` via `AdminMiddleware`)
| Method | Endpoint | Keterangan |
|--------|----------|-----------|
| POST | `/api/tables` | Buat tabel |
| PUT | `/api/tables/{id}` | Update tabel |
| DELETE | `/api/tables/{id}` | Delete tabel |
| GET | `/api/admin/bookings` | Monitor semua booking |
| GET | `/api/admin/payments` | Monitor transaksi |

Format error konsisten: `{ "message": "...", "errors": {...} }`.

## 9. Security

- Validasi semua input via Form Request
- Booking + payment memakai DB transaction
- Verifikasi signature webhook
- Prevent duplicate payment update (idempotent + unique `transaction_id`)
- Endpoint admin dilindungi `AdminMiddleware`

## 10. Testing

**Tanpa test otomatis** (sesuai keputusan). Verifikasi manual:
- `php artisan serve` + curl/Postman untuk endpoint
- `php artisan db:seed` → isi admin, user, tabel contoh lalu test dari sana
- Verifikasi `php artisan migrate` berjalan mulus di `billiard_dev`

## 11. Out of Scope (Advanced, opsional)

Email notification, QR check-in, promo/diskon, multi-branch, real-time availability (WebSocket).

## 12. Goal

Backend produksi-ready yang mendemonstrasikan integrasi payment, logika booking, konsistensi data, dan arsitektur scalable.
