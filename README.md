# Billiard API

> Sebuah sistem pemesanan meja billiard yang terkelola berbasis Laravel dan RESTful.

## Ringkasan

Billiard API memungkinkan pengguna untuk memesan meja billiard, membayar, dan melacak status pemesanan. Ini menyediakan role admin dan pengguna biasa.

## Cepat Sampai

### Jalankan Development

1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Setup database** (MySQL)
   - Buat database `billiard_dev`
   - `php artisan migrate`
   - `php artisan db:seed`

4. **Jalankan development server**
   ```bash
   composer dev
   ```

### Tingkatan admin
   - Login sebagai `admin@example.com` / `password`
   - Mengelola tabel (tambah / edit / hapus)
   - Melihat semua pemesanan & pembayaran

### Tingkatan user
   - Login sebagai `user@example.com` / `password`
   - Melihat meja
   - Buat / lihat pemesanan
   - Buat / lacak pembayaran

## Instalasi

### Requirement

- PHP 8.2+
- MySQL 5.7+ (atau MariaDB)
- Composer
- Node.js & npm
- Laravel 12.x

### Setup

```bash
# Instal package
composer install

# Instal package JS
npm install

# Copy environment & buat APP_KEY
cp .env.example .env
php artisan key:generate

# Buat database MySQL (default: billiard_dev)
# Import schema melalui migrasi Laravel

# Jalankan migrasi & seed
php artisan migrate
php artisan db:seed

# Build assets
npm run build
```

## Development

### Jalankan development server

```bash
# Semua-in-satu script (server + queue + logs + dev server)
composer dev

# Atau secara terpisah:
php artisan serve     # Server API + web
npm run dev           # Vite dev server
```

### Komposisi

- **Server:** `php artisan serve` (port 8000)
- **Queue:** `php artisan queue:listen` (default, dilibatkan dalam `composer dev`)
- **Logs:** `php artisan pail` (live log streaming, default)
- **Assets:** `npm run dev` (port 5173)

### Migrasi & Seeder

```bash
# Cek migrasi yang belum dijalankan
php artisan migrate:status

# Jalankan migrasi
php artisan migrate

# Jalankan seeder (membuat admin, user, tabel)
php artisan db:seed
```

## Database Schema

### Tabel utama

| Tabel | Kolom Utama | Relasi |
|-------|--------------|---------|
| **users** | `id`, `name`, `email`, `password`, `role` | `bookings()` (HasMany) |
| **tables** | `id`, `name`, `price_per_hour` | `bookings()` (HasMany) |
| **bookings** | `id`, `user_id`, `table_id`, `booking_date`, `start_time`, `end_time`, `total_price`, `status` | `user()` (BelongsTo), `table()` (BelongsTo), `payment()` (HasOne) |
| **payments** | `id`, `booking_id`, `payment_gateway`, `transaction_id`, `amount`, `status`, `paid_at` | `booking()` (BelongsTo) |
| **cache**, **jobs**, **sessions** | Laravel built-in | -

### ERD (text-only)

```
User
├── hasMany Bookings
    └── belongsTo Table
        └── hasOne Payment
```

### Definisi foreign keys

- `bookings.user_id` → `users.id`
- `bookings.table_id` → `tables.id`
- `payments.booking_id` → `bookings.id`

## API Reference

### Auth

| Endpoint | Method | Deskripsi | Response |
|----------|--------|------------|----------|
| `/register` | POST | Daftar akun baru | `{user, token}` |
| `/login` | POST | Login dengan email/password | `{user, token}` |
| `/logout` | POST | Logout & hapus token | `{}` |

### Bersama (Login dibutuhkan)

| Endpoint | Method | Deskripsi |
|----------|--------|------------|
| `/tables` | GET | Ambil semua meja |
| `/bookings` | POST | Buat pemesanan baru |
| `/bookings` | GET | Daftar pemesanan pengguna |
| `/bookings/{id}` | GET | Detail pemesanan |
| `/payment/create` | POST | Buat pembayaran untuk pemesanan |
| `/payment/status/{id}` | GET | Cek status pembayaran |

### Admin (login sebagai admin)

| Endpoint | Method | Deskripsi |
|----------|--------|------------|
| `/tables` | POST | Buat meja |
| `/tables/{id}` | PUT | Update meja |
| `/tables/{id}` | DELETE | Hapus meja |
| `/admin/bookings` | GET | Semua pemesanan (filterable: `status`, `booking_date`) |
| `/admin/payments` | GET | Semua pembayaran (filterable: `status`) |

### Webhook (untuk payment gateway)

| Endpoint | Method | Deskripsi |
|----------|--------|------------|
| `/payment/webhook` | POST | Webhook Xendit/Mock untuk update status pembayaran |

### Model JSON

Semua endpoint mengembalikan format:

```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

## Testing

Laravel menggunakan Pest (built-in). Jalankan:

```bash
# Semua tes
composer test

# Tes unit
php artisan test -- --filter="Unit"

# Tes feature
php artisan test -- --filter="Feature"
```

### Jalankan dengan option

```bash
# Verbose & color
php artisan test -vvv

# Hanya tes yang gagal
php artisan test -- --fail-on-empty
```

## Penggunaan

### Contoh pemesanan

```bash
# 1. Daftar & login
curl -X POST http://localhost:8000/register -d "name=John&email=john@example.com&password=secret"
curl -X POST http://localhost:8000/login -d "email=john@example.com&password=secret"

# 2. Ambil token & simpan
curl -X POST http://localhost:8000/login -d "email=john@example.com&password=secret"
# {"token": "..."}

# 3. Buat pemesanan (dengan token)
curl -X POST http://localhost:8000/bookings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"table_id": 1, "booking_date": "2025-01-01", "start_time": "10:00", "duration": 2}'

# 4. Buat pembayaran
curl -X POST http://localhost:8000/payment/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"booking_id": 1}'
```

## Configuration

### Environment Variables

| Key | Purpose | Default |
|-----|---------|---------|
| `DB_DATABASE` | Nama database | `billiard_dev` |
| `DB_USERNAME` | User MySQL | `root` |
| `APP_DEBUG` | Debug Laravel | `true` |
| `APP_ENV` | Environment | `local` |
| `PAYMENT_GATEWAY` | Gateway pembayaran yang digunakan | `mock` |
| `XENDIT_API_KEY` | Kunci API Xendit (opsional) | `–` |

### Payment Gateways

- **mock**: Generate invoice dummy, tidak perlu key API
- **xendit**: Gateway production (butuh `XENDIT_API_KEY` & `XENDIT_CALLBACK_TOKEN`)

## Deployment

### Persyaratan server

- PHP 8.2+
- MySQL 5.7+ (atau compatible)
- PHP extensions: `php pdo, php mbstring, php openssl`
- Node.js & npm

### Environment production tip

```bash
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error

# Buat file .env.production lalu:
php artisan config:clear
php artisan cache:clear
```

## License

MIT
