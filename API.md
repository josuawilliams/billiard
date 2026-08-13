# Dokumentasi API — Billiard Booking System

## 1. Pendahuluan

- **Base URL:** `http://localhost:8000/api`
- **Format:** Seluruh request & response menggunakan `application/json`.
- **Autentikasi:** Hampir semua endpoint (kecuali yang bertanda *Public*) membutuhkan token Bearer yang diperoleh dari `POST /api/login` atau `POST /api/register`.
  Header yang dikirim:
  ```
  Authorization: Bearer <token>
  ```
- **Akun seed:**
  | Email | Password | Role |
  |---|---|---|
  | `admin@example.com` | `password` | `admin` |
  | `user@example.com` | `password` | `user` |

## 2. Standar Response

### 2.1 Skema Success (`ApiResponse::success`)

Seluruh response sukses memakai envelope konsisten:

```json
{
  "success": true,
  "message": "<pesan>",
  "data": { ... }
}
```

- `data` bisa berupa objek, array, atau `null` (mis. `DELETE` atau `logout`).

### 2.2 Skema Error

Error yang dihasilkan aplikasi (`ApiResponse::error`) memakai envelope:

```json
{
  "success": false,
  "message": "<pesan>",
  "errors": { ... }   // opsional, hanya muncul jika field-level error
}
```

Catatan: error validasi (422) dan "Unauthenticated" (401) berasal dari framework Laravel, bukan dari `ApiResponse`, sehingga bentuknya berbeda (lihat detail di tiap endpoint).

### 2.3 Daftar HTTP Status

| Status | Arti |
|---|---|
| `200` | Sukses (GET, PUT, DELETE, dan proses selesai) |
| `201` | Resource berhasil dibuat (`register`, `POST /tables`, `POST /bookings`, `POST /payment/create`) |
| `401` | Autentikasi gagal: kredensial salah, token tidak valid/tidak ada, atau signature webhook salah |
| `403` | Forbidden: user biasa mengakses endpoint admin, atau mengakses resource milik user lain |
| `404` | Resource tidak ditemukan (id tidak ada) |
| `409` | Konflik: slot waktu booking sudah ter-book meja lain (double booking) |
| `422` | Validasi gagal, atau operasi tidak valid (mis. booking sudah dibayar) |

---

## 3. Endpoint

### Ringkasan

| Method | URL | Auth |
|---|---|---|
| `POST` | `/api/register` | Public |
| `POST` | `/api/login` | Public |
| `POST` | `/api/logout` | User |
| `GET` | `/api/tables` | User |
| `POST` | `/api/tables` | Admin |
| `PUT` | `/api/tables/{id}` | Admin |
| `DELETE` | `/api/tables/{id}` | Admin |
| `POST` | `/api/bookings` | User |
| `GET` | `/api/bookings` | User |
| `GET` | `/api/bookings/{id}` | User |
| `GET` | `/api/admin/bookings` | Admin |
| `POST` | `/api/payment/create` | User |
| `GET` | `/api/payment/status/{id}` | User |
| `POST` | `/api/payment/webhook` | Public |
| `GET` | `/api/admin/payments` | Admin |

---

## 3.1 Autentikasi

### POST /api/register

| | |
|---|---|
| Auth | Public |
| Body | `name` (string, required, max 255), `email` (email, required, unique), `password` (string, required, min 8), `password_confirmation` (string, required, harus sama dengan `password`) |

Contoh request:

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password","password_confirmation":"password"}'
```

Contoh response (201):

```json
{
  "success": true,
  "message": "Registered successfully",
  "data": {
    "user": {
      "id": 5,
      "name": "Test User",
      "email": "test@example.com",
      "email_verified_at": null,
      "role": "user",
      "created_at": "2026-08-13T03:47:32.000000Z",
      "updated_at": "2026-08-13T03:47:32.000000Z"
    },
    "token": "14|qYNLiekjQRTEy9LsxXJcViMQ9qL9TTqAotwpP9qI709d7db1"
  }
}
```

> User baru selalu dibuat dengan `role: "user"`.

Contoh response error (422 — validasi default Laravel):

```json
{
  "message": "The name field is required. (and 3 more errors)",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email field must be a valid email address."],
    "password": ["The password field must be at least 8 characters.", "The password field confirmation does not match."]
  }
}
```

---

### POST /api/login

| | |
|---|---|
| Auth | Public |
| Body | `email` (email, required), `password` (string, required) |

Contoh request:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "email_verified_at": null,
      "role": "admin",
      "created_at": "2026-08-13T03:28:53.000000Z",
      "updated_at": "2026-08-13T03:28:53.000000Z"
    },
    "token": "12|Kmon9ejFWlE901uMhIQDWoVRG0o7ei034gXpQ6Smc1c4cd54"
  }
}
```

Contoh response error (401):

```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### POST /api/logout

| | |
|---|---|
| Auth | User |
| Body | — |

Membatalkan token yang sedang dipakai (deleted).

Contoh request:

```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

Contoh response error (401 — token sudah tidak aktif):

```json
{
  "message": "Unauthenticated."
}
```

---

## 3.2 Tabel

### GET /api/tables

| | |
|---|---|
| Auth | User |
| Query | — |

Mengambil semua meja biliar.

Contoh request:

```bash
curl http://localhost:8000/api/tables \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Tables retrieved",
  "data": [
    {
      "id": 1,
      "name": "Meja A",
      "price_per_hour": "50000.00",
      "created_at": "2026-08-13T03:28:54.000000Z",
      "updated_at": "2026-08-13T03:28:54.000000Z"
    },
    {
      "id": 2,
      "name": "Meja B",
      "price_per_hour": "60000.00",
      "created_at": "2026-08-13T03:28:54.000000Z",
      "updated_at": "2026-08-13T03:28:54.000000Z"
    }
  ]
}
```

Contoh response error (401 — belum login):

```json
{
  "message": "Unauthenticated."
}
```

---

### POST /api/tables (khusus admin)

| | |
|---|---|
| Auth | Admin |
| Body | `name` (string, required, max 255), `price_per_hour` (numeric, required, min 0) |

Contoh request:

```bash
curl -X POST http://localhost:8000/api/tables \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <admin_token>" \
  -d '{"name":"Meja D","price_per_hour":80000}'
```

Contoh response (201):

```json
{
  "success": true,
  "message": "Table created",
  "data": {
    "name": "Meja D",
    "price_per_hour": "80000.00",
    "updated_at": "2026-08-13T03:47:55.000000Z",
    "created_at": "2026-08-13T03:47:55.000000Z",
    "id": 6
  }
}
```

Contoh response error (403 — dipanggil user biasa):

```json
{
  "success": false,
  "message": "Forbidden"
}
```

---

### PUT /api/tables/{id} (khusus admin)

| | |
|---|---|
| Auth | Admin |
| Body | `name` (string, optional), `price_per_hour` (numeric, optional, min 0) — minimal satu field |

Contoh request:

```bash
curl -X PUT http://localhost:8000/api/tables/7 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <admin_token>" \
  -d '{"name":"Meja E VIP","price_per_hour":95000}'
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Table updated",
  "data": {
    "id": 7,
    "name": "Meja E VIP",
    "price_per_hour": "95000.00",
    "created_at": "2026-08-13T03:48:02.000000Z",
    "updated_at": "2026-08-13T03:48:02.000000Z"
  }
}
```

Contoh response error (404 — id tidak ditemukan):

```json
{
  "message": "No query results for model [App\\Models\\Table] 9999"
}
```

---

### DELETE /api/tables/{id} (khusus admin)

| | |
|---|---|
| Auth | Admin |
| Body | — |

Contoh request:

```bash
curl -X DELETE http://localhost:8000/api/tables/7 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <admin_token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Table deleted",
  "data": null
}
```

---

## 3.3 Booking

### POST /api/bookings

| | |
|---|---|
| Auth | User |
| Body | `table_id` (integer, required, harus ada di tabel `tables`), `booking_date` (date `Y-m-d`, required, `>=` hari ini), `start_time` (string `H:i`, required), `duration` (integer, required, min 1, max 24 — dalam jam) |

`total_price` dihitung otomatis: `price_per_hour × duration`. Status awal booking: `pending`. Anti double-booking: jika slot meja pada tanggal & jam tersebut sudah ter-book (dengan status selain `cancelled`), response 409.

Contoh request:

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"table_id":1,"booking_date":"2026-08-15","start_time":"14:00","duration":2}'
```

Contoh response (201):

```json
{
  "success": true,
  "message": "Booking created",
  "data": {
    "user_id": 2,
    "table_id": 1,
    "booking_date": "2026-08-15T00:00:00.000000Z",
    "start_time": "14:00",
    "end_time": "16:00",
    "total_price": "100000.00",
    "status": "pending",
    "updated_at": "2026-08-13T03:47:38.000000Z",
    "created_at": "2026-08-13T03:47:38.000000Z",
    "id": 4,
    "table": {
      "id": 1,
      "name": "Meja A",
      "price_per_hour": "50000.00",
      "created_at": "2026-08-13T03:28:54.000000Z",
      "updated_at": "2026-08-13T03:28:54.000000Z"
    }
  }
}
```

Contoh response error (409 — double booking):

```json
{
  "success": false,
  "message": "Table is already booked for that time slot."
}
```

Contoh response error (422 — validasi, mis. `duration` di luar 1–24):

```json
{
  "message": "The duration field must be between 1 and 24.",
  "errors": {
    "duration": ["The duration field must be between 1 and 24."]
  }
}
```

---

### GET /api/bookings

| | |
|---|---|
| Auth | User |
| Query | — |

Mengambil seluruh booking milik user yang login, urut dari terbaru, dengan relasi `table` dan `payment`.

Contoh request:

```bash
curl http://localhost:8000/api/bookings \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Bookings retrieved",
  "data": [
    {
      "id": 4,
      "user_id": 2,
      "table_id": 1,
      "booking_date": "2026-08-15T00:00:00.000000Z",
      "start_time": "14:00:00",
      "end_time": "16:00:00",
      "total_price": "100000.00",
      "status": "pending",
      "created_at": "2026-08-13T03:47:38.000000Z",
      "updated_at": "2026-08-13T03:47:38.000000Z",
      "table": {
        "id": 1,
        "name": "Meja A",
        "price_per_hour": "50000.00",
        "created_at": "2026-08-13T03:28:54.000000Z",
        "updated_at": "2026-08-13T03:28:54.000000Z"
      },
      "payment": {
        "id": 1,
        "booking_id": 1,
        "payment_gateway": "mock",
        "transaction_id": "INV-1-OILN9D",
        "amount": "100000.00",
        "status": "success",
        "paid_at": "2026-08-13T03:41:10.000000Z",
        "created_at": "2026-08-13T03:41:02.000000Z",
        "updated_at": "2026-08-13T03:41:10.000000Z"
      }
    },
    {
      "id": 2,
      "user_id": 2,
      "table_id": 1,
      "booking_date": "2026-08-14T00:00:00.000000Z",
      "start_time": "16:00:00",
      "end_time": "17:00:00",
      "total_price": "50000.00",
      "status": "expired",
      "created_at": "2026-08-13T03:29:56.000000Z",
      "updated_at": "2026-08-13T03:45:56.000000Z",
      "table": { "id": 1, "name": "Meja A", "price_per_hour": "50000.00" },
      "payment": null
    }
  ]
}
```

> Status booking: `pending`, `paid`, `cancelled`, `expired`. Booking `pending` yang tidak dibayar dalam 15 menit otomatis menjadi `expired` (via scheduled job). Jika belum ada pembayaran, `payment` bernilai `null`.

---

### GET /api/bookings/{id}

| | |
|---|---|
| Auth | User |
| Path | `id` (int) — id booking |

Hanya pemilik booking yang dapat mengakses (selain itu → 403).

Contoh request:

```bash
curl http://localhost:8000/api/bookings/6 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Booking retrieved",
  "data": {
    "id": 6,
    "user_id": 2,
    "table_id": 3,
    "booking_date": "2026-08-15T00:00:00.000000Z",
    "start_time": "13:00:00",
    "end_time": "14:00:00",
    "total_price": "75000.00",
    "status": "paid",
    "created_at": "2026-08-13T03:47:54.000000Z",
    "updated_at": "2026-08-13T03:47:54.000000Z",
    "table": {
      "id": 3,
      "name": "Meja C",
      "price_per_hour": "75000.00",
      "created_at": "2026-08-13T03:28:54.000000Z",
      "updated_at": "2026-08-13T03:28:54.000000Z"
    },
    "payment": {
      "id": 4,
      "booking_id": 6,
      "payment_gateway": "mock",
      "transaction_id": "INV-6-NDRZEM",
      "amount": "75000.00",
      "status": "success",
      "paid_at": "2026-08-13T03:47:54.000000Z",
      "created_at": "2026-08-13T03:47:54.000000Z",
      "updated_at": "2026-08-13T03:47:54.000000Z"
    }
  }
}
```

Contoh response error (403 — booking milik user lain):

```json
{
  "success": false,
  "message": "Forbidden"
}
```

Contoh response error (404 — id tidak ditemukan):

```json
{
  "message": "No query results for model [App\\Models\\Booking] 9999"
}
```

---

### GET /api/admin/bookings (khusus admin)

| | |
|---|---|
| Auth | Admin |
| Query (opsional) | `status` (string, filter), `booking_date` (string `Y-m-d`, filter) |

Seluruh booking semua user, urut terbaru, paginated 20, dengan relasi `user`, `table`, `payment`.

Contoh request:

```bash
curl "http://localhost:8000/api/admin/bookings?status=pending" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <admin_token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Bookings retrieved",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 5,
        "user_id": 2,
        "table_id": 2,
        "booking_date": "2026-08-15T00:00:00.000000Z",
        "start_time": "10:00:00",
        "end_time": "12:00:00",
        "total_price": "120000.00",
        "status": "pending",
        "created_at": "2026-08-13T03:47:46.000000Z",
        "updated_at": "2026-08-13T03:47:46.000000Z",
        "user": {
          "id": 2,
          "name": "User Example",
          "email": "user@example.com",
          "email_verified_at": null,
          "role": "user",
          "created_at": "2026-08-13T03:28:54.000000Z",
          "updated_at": "2026-08-13T03:28:54.000000Z"
        },
        "table": {
          "id": 2,
          "name": "Meja B",
          "price_per_hour": "60000.00",
          "created_at": "2026-08-13T03:28:54.000000Z",
          "updated_at": "2026-08-13T03:28:54.000000Z"
        },
        "payment": {
          "id": 2,
          "booking_id": 5,
          "payment_gateway": "mock",
          "transaction_id": "INV-5-SOI9K3",
          "amount": "120000.00",
          "status": "pending",
          "paid_at": null,
          "created_at": "2026-08-13T03:47:46.000000Z",
          "updated_at": "2026-08-13T03:47:46.000000Z"
        }
      }
    ],
    "first_page_url": "http://127.0.0.1:8000/api/admin/bookings?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://127.0.0.1:8000/api/admin/bookings?page=1",
    "links": [],
    "next_page_url": null,
    "path": "http://127.0.0.1:8000/api/admin/bookings",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 3
  }
}
```

---

## 3.4 Pembayaran

### POST /api/payment/create

| | |
|---|---|
| Auth | User |
| Body | `booking_id` (integer, required, harus ada di tabel `bookings` dan milik user) |

Membuat pembayaran untuk booking `pending` milik user. Gateway aktif diambil dari `PAYMENT_GATEWAY` (`mock` default / `midtrans`). `transaction_id` dibuat otomatis berformat `INV-<booking_id>-<6 karakter acak>`. Untuk gateway `mock`, `snap_token` berformat `mock-<32 karakter acak>`.

Contoh request:

```bash
curl -X POST http://localhost:8000/api/payment/create \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"booking_id":5}'
```

Contoh response (201):

```json
{
  "success": true,
  "message": "Payment created. Redirect customer to Midtrans Snap.",
  "data": {
    "payment_id": 2,
    "transaction_id": "INV-5-SOI9K3",
    "amount": "120000.00",
    "status": "pending",
    "snap_token": "mock-zqR6bP5qLETYUMIwdhaJKpvuZfWFPPF5",
    "payment_gateway": "mock"
  }
}
```

Status payment: `pending`, `success`, `failed`.

Contoh response error (422 — booking sudah lunas / tidak bisa dibayar):

```json
{
  "success": false,
  "message": "Booking is not payable"
}
```

```json
{
  "success": false,
  "message": "Booking already paid"
}
```

---

### GET /api/payment/status/{id}

| | |
|---|---|
| Auth | User |
| Path | `id` (int) — id payment |

Hanya pemilik payment (melalui booking) yang dapat mengakses (selain itu → 403). Mengecek status pembayaran terbaru dari database.

Contoh request:

```bash
curl http://localhost:8000/api/payment/status/3 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Payment status retrieved",
  "data": {
    "id": 3,
    "booking_id": 5,
    "payment_gateway": "mock",
    "transaction_id": "INV-5-PCLKNB",
    "amount": "120000.00",
    "status": "pending",
    "paid_at": null,
    "created_at": "2026-08-13T03:47:46.000000Z",
    "updated_at": "2026-08-13T03:47:46.000000Z",
    "booking": {
      "id": 5,
      "user_id": 2,
      "table_id": 2,
      "booking_date": "2026-08-15T00:00:00.000000Z",
      "start_time": "10:00:00",
      "end_time": "12:00:00",
      "total_price": "120000.00",
      "status": "pending",
      "created_at": "2026-08-13T03:47:46.000000Z",
      "updated_at": "2026-08-13T03:47:46.000000Z"
    }
  }
}
```

Contoh response error (403 — payment milik user lain):

```json
{
  "success": false,
  "message": "Forbidden"
}
```

---

### POST /api/payment/webhook (Public)

| | |
|---|---|
| Auth | Public |

Webhook dari gateway (Midtrans) yang menandakan transaksi diproses. Payload berisi minimal:

| Field | Tipe | Keterangan |
|---|---|---|
| `order_id` | string | `transaction_id` payment (mis. `INV-5-SOI9K3`) |
| `status_code` | string | Kode status Midtrans |
| `gross_amount` | string | Jumlah pembayaran |
| `transaction_status` | string | Salah satu: `capture`, `settlement` (sukses), atau lainnya (gagal) |
| `signature_key` | string | Signature untuk verifikasi `sha512(order_id.status_code.gross_amount.server_key)` |

**Cara menghitung signature (gateway `mock`, server key `mock-server-key`):**

```bash
SERVER_KEY="mock-server-key"  # dari config('payment.mock.server_key')
echo -n "INV-6-NDRZEM20075000.00${SERVER_KEY}" | shasum -a 512
# = e97b71f4d84f11cbf6bbc87f773bd9c715ef1b4c63c1b1ce6aa3af40faa7b2b6a9d20ae5fbbcb0169cc14b1b6c9a0b1b92a1a110b91d5058d0daf86ba6990493
```

Signature dihitung sebagai `sha512(order_id + status_code + gross_amount + server_key)` lalu dibandingkan menggunakan `hash_equals` dengan `signature_key`.

Contoh request (webhook sukses):

```bash
curl -X POST http://localhost:8000/api/payment/webhook \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "order_id": "INV-6-NDRZEM",
    "status_code": "200",
    "gross_amount": "75000.00",
    "transaction_status": "settlement",
    "signature_key": "e97b71f4d84f11cbf6bbc87f773bd9c715ef1b4c63c1b1ce6aa3af40faa7b2b6a9d20ae5fbbcb0169cc14b1b6c9a0b1b92a1a110b91d5058d0daf86ba6990493"
  }'
```

Contoh response (200 — transaksi sukses, booking otomatis menjadi `paid`):

```json
{
  "success": true,
  "message": "Webhook processed",
  "data": {
    "id": 4,
    "booking_id": 6,
    "payment_gateway": "mock",
    "transaction_id": "INV-6-NDRZEM",
    "amount": "75000.00",
    "status": "success",
    "paid_at": "2026-08-13T03:47:54.000000Z",
    "created_at": "2026-08-13T03:47:54.000000Z",
    "updated_at": "2026-08-13T03:47:54.000000Z"
  }
}
```

> **Idempotent:** jika payment sudah berstatus `success`, webhook yang di-replay mengembalikan `200` dengan pesan `"Payment already processed"` tanpa mengubah data.

Contoh response (200 — replay, sudah diproses):

```json
{
  "success": true,
  "message": "Payment already processed",
  "data": { ... }
}
```

Contoh response (200 — webhook dengan `transaction_status` selain `capture`/`settlement`, payment menjadi `failed`):

```json
{
  "success": true,
  "message": "Webhook processed",
  "data": {
    "id": 6,
    "booking_id": 8,
    "payment_gateway": "mock",
    "transaction_id": "INV-8-TMKB2A",
    "amount": "50000.00",
    "status": "failed",
    "paid_at": null,
    "created_at": "2026-08-13T03:48:03.000000Z",
    "updated_at": "2026-08-13T03:48:03.000000Z"
  }
}
```

Contoh response error (401 — signature salah):

```json
{
  "success": false,
  "message": "Invalid signature"
}
```

Contoh response error (404 — `order_id` tidak ditemukan):

```json
{
  "success": false,
  "message": "Payment not found"
}
```

---

### GET /api/admin/payments (khusus admin)

| | |
|---|---|
| Auth | Admin |
| Query (opsional) | `status` (string: `pending` / `success` / `failed`) |

Seluruh payment beserta booking + user + table, urut terbaru, paginated 20.

Contoh request:

```bash
curl http://localhost:8000/api/admin/payments \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <admin_token>"
```

Contoh response (200):

```json
{
  "success": true,
  "message": "Payments retrieved",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 2,
        "booking_id": 5,
        "payment_gateway": "mock",
        "transaction_id": "INV-5-SOI9K3",
        "amount": "120000.00",
        "status": "pending",
        "paid_at": null,
        "created_at": "2026-08-13T03:47:46.000000Z",
        "updated_at": "2026-08-13T03:47:46.000000Z",
        "booking": {
          "id": 5,
          "user_id": 2,
          "table_id": 2,
          "booking_date": "2026-08-15T00:00:00.000000Z",
          "start_time": "10:00:00",
          "end_time": "12:00:00",
          "total_price": "120000.00",
          "status": "pending",
          "created_at": "2026-08-13T03:47:46.000000Z",
          "updated_at": "2026-08-13T03:47:46.000000Z",
          "user": {
            "id": 2,
            "name": "User Example",
            "email": "user@example.com",
            "email_verified_at": null,
            "role": "user",
            "created_at": "2026-08-13T03:28:54.000000Z",
            "updated_at": "2026-08-13T03:28:54.000000Z"
          },
          "table": {
            "id": 2,
            "name": "Meja B",
            "price_per_hour": "60000.00",
            "created_at": "2026-08-13T03:28:54.000000Z",
            "updated_at": "2026-08-13T03:28:54.000000Z"
          }
        }
      }
    ],
    "first_page_url": "http://127.0.0.1:8000/api/admin/payments?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://127.0.0.1:8000/api/admin/payments?page=1",
    "links": [],
    "next_page_url": null,
    "path": "http://127.0.0.1:8000/api/admin/payments",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 3
  }
}
```

Contoh response error (403 — dipanggil user biasa):

```json
{
  "success": false,
  "message": "Forbidden"
}
```