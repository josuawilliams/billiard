# Billiard Booking System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun backend API Billiard Booking System dengan Laravel 12 — auth Sanctum, booking dengan anti double-booking, payment Midtrans (mock di development), dan auto-expire booking — plus dokumentasi API terpadu di satu file `API.md`.

**Architecture:** Controller tipis → Service (logika bisnis) → Model. Validasi memakai Form Request. Payment lewat interface `PaymentGateway` yang di-resolve oleh `PaymentService` (mock di dev, midtrans di prod). Response JSON seragam `{success, message, data|errors}` via helper `ApiResponse`. Tanpa test otomatis; setiap task diverifikasi manual dengan curl/artisan.

**Tech Stack:** Laravel 12 (PHP 8.2), Laravel Sanctum, MySQL (`billiard_dev`), Midtrans Snap API, Laravel Queue + Scheduler.

## Global Constraints

- DB MySQL tunggal `billiard_dev` untuk semua. Jangan buat DB lain.
- Tanpa file test otomatis. Jangan menulis `tests/Feature/*` atau `tests/Unit/*` baru.
- Semua response memakai helper `ApiResponse` — output format konsisten.
- Semua route API di `routes/api.php` dengan prefix `/api`.
- Endpoint admin hanya untuk `role = admin`, lewat middleware alias `admin`.
- `transaction_id` pada payments selalu unique.
- Seeder: admin `admin@example.com`/`password`, user `user@example.com`/`password`, 4 tabel contoh.
- Dokumentasi API final: satu file `API.md` di root project. Tanpa dokumentasi tambahan selain itu (spec/plan tetap di `docs/`).
- Dokumen ini mengikuti spec: `docs/superpowers/specs/2026-08-13-billiard-booking-design.md`.

---

### Task 1: Environment — MySQL & Database

**Files:**
- Modify: `.env` (DB section)
- Modify: `.env.example` (DB section)

**Interfaces:**
- Produces: MySQL server berjalan dengan database `billiard_dev` yang bisa dikoneksi oleh Laravel.

- [ ] **Step 1: Pastikan MySQL berjalan**

Run:
```bash
mysqladmin ping 2>&1 | head -1
```
Expected: `mysqld is alive` (jika `Can't connect`, jalankan `brew services start mysql` lalu tunggu ~5 detik dan ulangi).

- [ ] **Step 2: Buat database `billiard_dev`**

Run:
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS billiard_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Jika gagal karena password root, tanya user password MySQL dulu, lalu jalankan dengan `-p`.

- [ ] **Step 3: Update `.env`**

Ubah bagian DB menjadi:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billiard_dev
DB_USERNAME=root
DB_PASSWORD=
```
(isi `DB_PASSWORD` sesuai password root user)

- [ ] **Step 4: Update `.env.example`**

Ubah bagian DB `.env.example` sama seperti Step 3 (dengan nilai placeholder yang masuk akal, mis `DB_PASSWORD=`).

- [ ] **Step 5: Verifikasi koneksi**

Run:
```bash
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); var_dump(DB::connection()->getPdo() ? 'CONNECTED' : 'FAIL');"
echo '---'; php -r "\$p = new PDO('mysql:host=127.0.0.1;dbname=billiard_dev','root',''); echo 'PDO OK';"
```
Expected: `CONNECTED` dan `PDO OK`.

- [ ] **Step 6: Commit**

```bash
git add .env .env.example
git commit -m "chore: use MySQL billiard_dev database"
```

---

### Task 2: Sanctum + API Routes + Response Helper

**Files:**
- Create: `routes/api.php` (digenerate `install:api`, lalu diganti isinya)
- Modify: `bootstrap/app.php` (register api routing + middleware alias `admin`)
- Create: `app/Support/ApiResponse.php`

**Interfaces:**
- Produces:
  - `\App\Support\ApiResponse::success(mixed $data, string $message, int $status = 200): \Illuminate\Http\JsonResponse`
  - `\App\Support\ApiResponse::error(string $message, int $status, mixed $errors = null): \Illuminate\Http\JsonResponse`
  - Middleware alias `admin` tersedia di routing.

- [ ] **Step 1: Install Sanctum & API scaffolding**

Run:
```bash
composer require laravel/sanctum
php artisan install:api
```
Expected: kedua perintah selesai tanpa error; muncul `routes/api.php`, `config/sanctum.php`, dan migration `personal_access_tokens`.

- [ ] **Step 2: Update `bootstrap/app.php`**

Isi penuh menjadi:
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```
Catatan: `\App\Http\Middleware\AdminMiddleware` akan dibuat di Task 5. Alias ini boleh merujuk kelas yang belum ada terlebih dahulu.

- [ ] **Step 3: Buat `app/Support/ApiResponse.php`**

```php
<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
```

- [ ] **Step 4: Isi `routes/api.php` sementara (dummy sehat)**

Ganti isi `routes/api.php` seluruhnya menjadi:
```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return \App\Support\ApiResponse::success(['pong' => true], 'Pong');
});
```

- [ ] **Step 5: Verifikasi**

Run:
```bash
php artisan route:list --path=api
curl -s http://localhost:8000/api/ping
```
Expected: route `api/ping` terdaftar; curl mengembalikan `{"success":true,"message":"Pong","data":{"pong":true}}`. Jika server belum jalan, jalankan `php artisan serve` di terminal terpisah dulu.

- [ ] **Step 6: Commit**

```bash
git add routes/api.php bootstrap/app.php app/Support/ApiResponse.php composer.json composer.lock
git commit -m "feat: add sanctum, api routes, and ApiResponse helper"
```

---

### Task 3: Migrations & Models

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (tambah kolom role)
- Create: `database/migrations/2026_08_13_000001_create_tables_table.php`
- Create: `database/migrations/2026_08_13_000002_create_bookings_table.php`
- Create: `database/migrations/2026_08_13_000003_create_payments_table.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/Table.php`, `app/Models/Booking.php`, `app/Models/Payment.php`

**Interfaces:**
- Produces:
  - `App\Models\Table { id, name, price_per_hour }`
  - `App\Models\Booking { id, user_id, table_id, booking_date, start_time, end_time, total_price, status }`, relasi `user()`, `table()`, `payment()`
  - `App\Models\Payment { id, booking_id, payment_gateway, transaction_id (unique), amount, status, paid_at }`, relasi `booking()`
  - `App\Models\User` dengan kolom `role`.

- [ ] **Step 1: Tambah kolom `role` di migration users**

Di dalam `up()` migration users, pada bagian setelah `password`, tambahkan:
```php
$table->enum('role', ['user', 'admin'])->default('user');
```
Catatan: kolom default Laravel (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps) tetap dipertahankan.

- [ ] **Step 2: Buat migration `tables`**

`database/migrations/2026_08_13_000001_create_tables_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_per_hour', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
```

- [ ] **Step 3: Buat migration `bookings`**

`database/migrations/2026_08_13_000002_create_bookings_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('table_id')->constrained('tables')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired'])->default('pending');
            $table->timestamps();

            $table->index(['table_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
```

- [ ] **Step 4: Buat migration `payments`**

`database/migrations/2026_08_13_000003_create_payments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('payment_gateway');
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 5: Update `User.php`**

Modifikasi `app/Models/User.php`: tambahkan `'role'` ke `$fillable`:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];
```

- [ ] **Step 6: Buat `Table.php`**

`app/Models/Table.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price_per_hour',
    ];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
```

- [ ] **Step 7: Buat `Booking.php`**

`app/Models/Booking.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'table_id',
        'booking_date',
        'start_time',
        'end_time',
        'total_price',
        'status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'booking_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
```

- [ ] **Step 8: Buat `Payment.php`**

`app/Models/Payment.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payment_gateway',
        'transaction_id',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
```

- [ ] **Step 9: Jalankan migration**

Run:
```bash
php artisan migrate
```
Expected: semua migration sukses; muncul tabel `users` (dengan role), `tables`, `bookings`, `payments`, `personal_access_tokens`, dll. Cek dengan:
```bash
mysql -u root billiard_dev -e "SHOW TABLES;"
```

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Models
git commit -m "feat: add tables, bookings, payments schema and models"
```

---

### Task 4: Seeder (Admin, User Contoh, Tabel)

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: minimal data `admin@example.com`/`password` (role admin), `user@example.com`/`password` (role user), 4 tabel.

- [ ] **Step 1: Isi `DatabaseSeeder.php`**

`database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'User Example',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        Table::create(['name' => 'Meja A', 'price_per_hour' => 50000]);
        Table::create(['name' => 'Meja B', 'price_per_hour' => 60000]);
        Table::create(['name' => 'Meja C', 'price_per_hour' => 75000]);
        Table::create(['name' => 'Meja VIP', 'price_per_hour' => 100000]);
    }
}
```

- [ ] **Step 2: Jalankan seeder**

Run:
```bash
php artisan db:seed
```
Expected: 2 user + 4 tabel masuk. Verifikasi:
```bash
mysql -u root billiard_dev -e "SELECT id,name,email,role FROM users; SELECT id,name,price_per_hour FROM tables;"
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: seed admin, sample user, and billiard tables"
```

---

### Task 5: Authentication (Register, Login, Logout)

**Files:**
- Create: `app/Http/Controllers/AuthController.php`
- Create: `app/Http/Requests/RegisterRequest.php`
- Create: `app/Http/Requests/LoginRequest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `ApiResponse`, `App\Models\User`.
- Produces:
  - `POST /api/register` → 201 `{success, message, data:{user, token}}`
  - `POST /api/login` → 200 `{success, message, data:{user, token}}`; gagal → 401
  - `POST /api/logout` (auth) → 200 `{success, message}`

- [ ] **Step 1: Buat `RegisterRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

- [ ] **Step 2: Buat `LoginRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 3: Buat `AuthController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], 'Registered successfully', 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }
}
```

- [ ] **Step 4: Update `routes/api.php`**

Ganti isi seluruh `routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

- [ ] **Step 5: Verifikasi manual**

Server jalan (`php artisan serve`). Lalu:
```bash
curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```
Expected: 200 dengan `success:true`, `data.token` ada. Simpan token untuk task berikutnya (export `TOKEN=...`).
```bash
curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"salah"}'
```
Expected: 401 `{"success":false,"message":"Invalid credentials"}`.
```bash
curl -s -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```
Expected: 200 `{"success":true,...}`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AuthController.php app/Http/Requests routes/api.php
git commit -m "feat: register login logout authentication"
```

---

### Task 6: Admin Middleware + CRUD Table (Admin)

**Files:**
- Create: `app/Http/Middleware/AdminMiddleware.php`
- Create: `app/Http/Controllers/TableController.php`
- Create: `app/Http/Requests/StoreTableRequest.php`
- Create: `app/Http/Requests/UpdateTableRequest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: alias `admin` (dari Task 2), `ApiResponse`, `App\Models\Table`.
- Produces:
  - `GET /api/tables` (auth) → 200 list tabel
  - `POST /api/tables` (admin) → 201
  - `PUT /api/tables/{id}` (admin) → 200
  - `DELETE /api/tables/{id}` (admin) → 200
  - Middleware alias class `\App\Http\Middleware\AdminMiddleware::class`.

- [ ] **Step 1: Buat `AdminMiddleware.php`**

```php
<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return ApiResponse::error('Forbidden', 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Buat `StoreTableRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 3: Buat `UpdateTableRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price_per_hour' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Buat `TableController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use App\Support\ApiResponse;

class TableController extends Controller
{
    public function index()
    {
        return ApiResponse::success(Table::all(), 'Tables retrieved');
    }

    public function store(StoreTableRequest $request)
    {
        $table = Table::create($request->validated());

        return ApiResponse::success($table, 'Table created', 201);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $table->update($request->validated());

        return ApiResponse::success($table, 'Table updated');
    }

    public function destroy(Table $table)
    {
        $table->delete();

        return ApiResponse::success(null, 'Table deleted');
    }
}
```

- [ ] **Step 5: Update `routes/api.php`**

Ganti isi seluruh `routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tables', [TableController::class, 'index']);

    Route::middleware('admin')->group(function () {
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::delete('/tables/{table}', [TableController::class, 'destroy']);
    });
});
```

- [ ] **Step 6: Verifikasi manual**

```bash
# login admin
TOKEN_ADMIN=$(curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"admin@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')
# login user biasa
TOKEN_USER=$(curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"user@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

# admin create table
curl -s -X POST http://localhost:8000/api/tables -H "Authorization: Bearer $TOKEN_ADMIN" -H "Content-Type: application/json" -H "Accept: application/json" -d '{"name":"Meja D","price_per_hour":70000}'
# Expected: 201 success true

# user biasa create table -> harus 403
curl -s -X POST http://localhost:8000/api/tables -H "Authorization: Bearer $TOKEN_USER" -H "Content-Type: application/json" -H "Accept: application/json" -d '{"name":"Meja E","price_per_hour":70000}'
# Expected: 403 {"success":false,"message":"Forbidden"}

# list tables (auth)
curl -s http://localhost:8000/api/tables -H "Authorization: Bearer $TOKEN_USER" -H "Accept: application/json"
# Expected: 200 success true dengan array tables

# update & delete pakai $TOKEN_ADMIN
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/AdminMiddleware.php app/Http/Controllers/TableController.php app/Http/Requests routes/api.php
git commit -m "feat: admin table CRUD with admin middleware"
```

---

### Task 7: Booking + Anti Double-Booking

**Files:**
- Create: `app/Http/Controllers/BookingController.php`
- Create: `app/Http/Requests/StoreBookingRequest.php`
- Create: `app/Services/BookingService.php`
- Create: `app/Exceptions/BookingConflictException.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `ApiResponse`, `App\Models\Booking`, `App\Models\Table`, middleware `auth:sanctum` & `admin`.
- Produces:
  - `BookingService::create(array $data, User $user): Booking` — melakukan validasi overlap & create. Melempar `BookingConflictException` pada tabrakan.
  - `POST /api/bookings` → 201 atau 409; `GET /api/bookings` → 200 (milik user); `GET /api/bookings/{id}` → 200/403.
  - `GET /api/admin/bookings` (admin) → 200 list semua booking.
  - Request body: `table_id`, `booking_date` (Y-m-d), `start_time` (H:i), `duration` (jam integer 1-24).

- [ ] **Step 1: Buat `BookingConflictException.php`**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException
{
}
```

- [ ] **Step 2: Buat `StoreBookingRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'booking_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'min:1', 'max:24'],
        ];
    }
}
```

- [ ] **Step 3: Buat `BookingService.php`**

```php
<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function create(array $data, User $user): Booking
    {
        $table = Table::findOrFail($data['table_id']);

        $start = Carbon::parse($data['start_time']);
        $end = $start->copy()->addHours((int) $data['duration']);

        $totalPrice = $table->price_per_hour * (int) $data['duration'];

        return DB::transaction(function () use ($data, $user, $table, $start, $end, $totalPrice) {
            $overlap = Booking::where('table_id', $table->id)
                ->where('booking_date', $data['booking_date'])
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '<', $end->format('H:i'))
                ->where('end_time', '>', $data['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new BookingConflictException('Table is already booked for that time slot.');
            }

            return Booking::create([
                'user_id' => $user->id,
                'table_id' => $table->id,
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $end->format('H:i'),
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);
        });
    }
}
```

- [ ] **Step 4: Buat `BookingController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingConflictException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with('table', 'payment')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($bookings, 'Bookings retrieved');
    }

    public function store(StoreBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->create($request->validated(), $request->user());

            return ApiResponse::success($booking->load('table'), 'Booking created', 201);
        } catch (BookingConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }
    }

    public function show(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success($booking->load('table', 'payment'), 'Booking retrieved');
    }

    public function adminBookings(Request $request)
    {
        $bookings = Booking::with('user', 'table', 'payment')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->booking_date, fn ($q, $date) => $q->where('booking_date', $date))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success($bookings, 'Bookings retrieved');
    }
}
```

- [ ] **Step 5: Update `routes/api.php`**

Ganti isi seluruh `routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tables', [TableController::class, 'index']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);

    Route::middleware('admin')->group(function () {
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::delete('/tables/{table}', [TableController::class, 'destroy']);
        Route::get('/admin/bookings', [BookingController::class, 'adminBookings']);
    });
});
```

- [ ] **Step 6: Verifikasi manual**

```bash
TOKEN_USER=$(curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"user@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

# booking sukses (Meja 1, besok, 14:00 - 16:00)
curl -s -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer $TOKEN_USER" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"table_id":1,"booking_date":"2026-08-14","start_time":"14:00","duration":2}'
# Expected: 201 success true, total_price 100000.00, end_time 16:00

# overlap -> 409
curl -s -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer $TOKEN_USER" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"table_id":1,"booking_date":"2026-08-14","start_time":"15:00","duration":2}'
# Expected: 409 {"success":false,"message":"Table is already booked for that time slot."}

# berdekatan tapi tidak overlap -> 201 (14:00-16:00 vs 16:00-17:00)
curl -s -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer $TOKEN_USER" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"table_id":1,"booking_date":"2026-08-14","start_time":"16:00","duration":1}'
# Expected: 201

# list & show booking
curl -s http://localhost:8000/api/bookings -H "Authorization: Bearer $TOKEN_USER" -H "Accept: application/json"
curl -s http://localhost:8000/api/bookings/1 -H "Authorization: Bearer $TOKEN_USER" -H "Accept: application/json"
# Expected: 200
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/BookingController.php app/Http/Requests/StoreBookingRequest.php app/Services/BookingService.php app/Exceptions/BookingConflictException.php routes/api.php
git commit -m "feat: booking with anti double-booking logic"
```

---

### Task 8: Payment (Mock + Midtrans Gateway) & Webhook

**Files:**
- Create: `config/payment.php`
- Create: `app/Services/Gateways/PaymentGateway.php` (interface)
- Create: `app/Services/Gateways/MockGateway.php`
- Create: `app/Services/Gateways/MidtransGateway.php`
- Create: `app/Services/PaymentService.php`
- Create: `app/Http/Controllers/PaymentController.php`
- Create: `app/Http/Requests/CreatePaymentRequest.php`
- Modify: `routes/api.php`
- Modify: `.env`, `.env.example` (payment keys)
- Modify: `config/services.php` (midtrans)

**Interfaces:**
- Consumes: `App\Models\Booking`, `App\Models\Payment`, `ApiResponse`, gateway config.
- Produces:
  - `PaymentGateway` interface:
    ```php
    public function getName(): string;
    public function createTransaction(Payment $payment): array; // ['snap_token' => string]
    public function verifyWebhookSignature(array $payload): bool;
    ```
  - `PaymentService::createPayment(Booking $booking): array` → `['payment_id','transaction_id','amount','status','snap_token','payment_gateway']`
  - `POST /api/payment/create` → 201; `GET /api/payment/status/{payment}` → 200; `POST /api/payment/webhook` → 200; `GET /api/admin/payments` (admin) → 200.
  - Webhook verifikasi signature `SHA512(order_id + status_code + gross_amount + server_key)`.

- [ ] **Step 1: Buat `config/payment.php`**

```php
<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'mock' => [
        'server_key' => env('PAYMENT_MOCK_SERVER_KEY', 'mock-server-key'),
    ],
];
```

- [ ] **Step 2: Update `config/services.php`** — tambahkan di akhir array:

```php
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],
```

- [ ] **Step 3: Update `.env` & `.env.example`** — tambahkan:

```
PAYMENT_GATEWAY=mock
PAYMENT_MOCK_SERVER_KEY=mock-server-key
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

- [ ] **Step 4: Buat interface `PaymentGateway.php`**

```php
<?php

namespace App\Services\Gateways;

use App\Models\Payment;

interface PaymentGateway
{
    public function getName(): string;

    public function createTransaction(Payment $payment): array;

    public function verifyWebhookSignature(array $payload): bool;
}
```

- [ ] **Step 5: Buat `MockGateway.php`**

```php
<?php

namespace App\Services\Gateways;

use App\Models\Payment;
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
            'snap_token' => 'mock-'.Str::random(32),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
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

- [ ] **Step 6: Buat `MidtransGateway.php`**

```php
<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGateway
{
    public function getName(): string
    {
        return 'midtrans';
    }

    public function createTransaction(Payment $payment): array
    {
        $booking = $payment->booking;
        $servers = config('midtrans.is_production', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $serverKey = config('midtrans.server_key');

        $response = Http::withBasicAuth($serverKey, '')
            ->post($servers, [
                'transaction_details' => [
                    'order_id' => $payment->transaction_id,
                    'gross_amount' => (int) round((float) $booking->total_price),
                ],
                'customer_details' => [
                    'first_name' => $booking->user->name,
                    'email' => $booking->user->email,
                ],
            ]);

        $response->throw();

        return [
            'snap_token' => $response->json('token'),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $serverKey = config('midtrans.server_key');

        if (! $signature || ! $orderId || ! $statusCode || ! $grossAmount || ! $serverKey) {
            return false;
        }

        return hash_equals(
            hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey),
            $signature
        );
    }
}
```

- [ ] **Step 7: Buat `PaymentService.php`**

```php
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Gateways\MidtransGateway;
use App\Services\Gateways\MockGateway;
use App\Services\Gateways\PaymentGateway;
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
    }

    public function verifyWebhook(array $payload): bool
    {
        return $this->resolveGateway()->verifyWebhookSignature($payload);
    }
}
```

- [ ] **Step 8: Buat `CreatePaymentRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ];
    }
}
```

- [ ] **Step 9: Buat `PaymentController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Models\Booking;
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

        return ApiResponse::success($result, 'Payment created. Redirect customer to Midtrans Snap.', 201);
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

        if (! $this->paymentService->verifyWebhook($payload)) {
            return ApiResponse::error('Invalid signature', 401);
        }

        $payment = Payment::where('transaction_id', $payload['order_id'])->first();

        if (! $payment) {
            return ApiResponse::error('Payment not found', 404);
        }

        if ($payment->status === 'success') {
            return ApiResponse::success($payment, 'Payment already processed');
        }

        $isSuccess = in_array($payload['transaction_status'] ?? null, ['capture', 'settlement']);

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
Catatan: `$request->user()->bookings()` ada karena relasi `User hasMany Booking` belum didefinisikan di model User. Tambahkan relasi tersebut di `app/Models/User.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// di dalam class User:
public function bookings(): HasMany
{
    return $this->hasMany(Booking::class);
}
```

- [ ] **Step 10: Update `routes/api.php`**

Ganti isi seluruh `routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tables', [TableController::class, 'index']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);

    Route::post('/payment/create', [PaymentController::class, 'create']);
    Route::get('/payment/status/{payment}', [PaymentController::class, 'status']);

    Route::middleware('admin')->group(function () {
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::delete('/tables/{table}', [TableController::class, 'destroy']);
        Route::get('/admin/bookings', [BookingController::class, 'adminBookings']);
        Route::get('/admin/payments', [PaymentController::class, 'adminPayments']);
    });
});
```

- [ ] **Step 11: Verifikasi manual**

```bash
TOKEN_USER=$(curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"user@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

# create payment untuk booking id 1
curl -s -X POST http://localhost:8000/api/payment/create \
  -H "Authorization: Bearer $TOKEN_USER" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"booking_id":1}'
# Expected: 201 success true, snap_token = "mock-..."

# cek status
curl -s http://localhost:8000/api/payment/status/1 -H "Authorization: Bearer $TOKEN_USER" -H "Accept: application/json"
# Expected: 200 success true, status "pending"

# simulasikan webhook sukses. order_id = transaction_id dari create di atas.
# signature = sha512(order_id + "200" + "100000.00" + "mock-server-key")
ORDER_ID='<transaction_id dari create>'
SIG=$(php -r 'echo hash("sha512", $argv[1].$argv[2].$argv[3].$argv[4]);' "$ORDER_ID" 200 100000.00 mock-server-key)
curl -s -X POST http://localhost:8000/api/payment/webhook \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"order_id\":\"$ORDER_ID\",\"status_code\":\"200\",\"gross_amount\":\"100000.00\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"signature_key\":\"$SIG\"}"
# Expected: 200 success true, payment status success

# webhook dua kali -> idempotent, tetap success tanpa error
# webhook signature salah -> 401

# cek booking jadi paid
curl -s http://localhost:8000/api/bookings/1 -H "Authorization: Bearer $TOKEN_USER" -H "Accept: application/json"
# Expected: status "paid"

# admin payments
TOKEN_ADMIN=$(curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"admin@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')
curl -s http://localhost:8000/api/admin/payments -H "Authorization: Bearer $TOKEN_ADMIN" -H "Accept: application/json"
# Expected: 200
```

- [ ] **Step 12: Commit**

```bash
git add config/payment.php config/services.php .env .env.example app/Services app/Http/Controllers/PaymentController.php app/Http/Requests/CreatePaymentRequest.php app/Models/User.php routes/api.php
git commit -m "feat: payment gateway (mock/midtrans) create, status, webhook"
```

---

### Task 9: Auto-Expire Booking (Scheduler + Job)

**Files:**
- Create: `app/Jobs/ExpireBookingJob.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: `App\Models\Booking`.
- Produces: `ExpireBookingJob` (ShouldQueue) — job berjalan via scheduler tiap menit yang men-expire booking `pending` >15 menit tanpa payment success.

- [ ] **Step 1: Buat `ExpireBookingJob.php`**

```php
<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBookingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Booking::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', 'success'))
            ->update(['status' => 'expired']);
    }
}
```

- [ ] **Step 2: Update `routes/console.php`**

```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::job(new App\Jobs\ExpireBookingJob)->everyMinute();
```
(hapus command `inspire` bawaan atau biarkan; yang penting baris `Schedule` ditambahkan). Import yang benar di atas:

```php
<?php

use App\Jobs\ExpireBookingJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireBookingJob)->everyMinute();
```

- [ ] **Step 3: Jalankan queue worker + scheduler utk dev**

Run (dua perintah, terminal terpisah atau pakai `composer dev` — `dev` script sudah menjalankan `queue:listen`):
```bash
php artisan schedule:work
```
Expected: job tiap menit berjalan.

- [ ] **Step 4: Verifikasi manual**

```bash
# buat booking pending cepat expire dengan merusak created_at
mysql -u root billiard_dev -e "UPDATE bookings SET created_at = DATE_SUB(NOW(), INTERVAL 16 MINUTE) WHERE status='pending' LIMIT 1;"
php artisan dispatch App\\Jobs\\ExpireBookingJob
mysql -u root billiard_dev -e "SELECT id,status FROM bookings WHERE status='expired';"
```
Expected: ada booking dengan status `expired`.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ExpireBookingJob.php routes/console.php
git commit -m "feat: auto-expire pending bookings after 15 minutes"
```

---

### Task 10: Dokumentasi API Terpadu (`API.md`)

**Files:**
- Create: `API.md` (root project)

**Interfaces:**
- Consumes: seluruh endpoint dari Task 5-8 & skema response dari Task 2.

- [ ] **Step 1: Buat `API.md`**

Tulis `API.md` di root project dengan struktur lengkap (lihat konten wajib di bawah). `API.md` wajib memuat:
1. **Pendahuluan** — base URL (`http://localhost:8000/api`), format request/response JSON, autentikasi Bearer token.
2. **Standar Response** — skema success/error envelope + daftar HTTP status yang dipakai.
3. **Setiap endpoint** (dalam tabel ringkas + detail sub-bagian per endpoint):
   - `POST /register`, `POST /login`, `POST /logout`
   - `GET /tables`, `POST /tables`, `PUT /tables/{id}`, `DELETE /tables/{id}`
   - `POST /bookings`, `GET /bookings`, `GET /bookings/{id}`, `GET /admin/bookings`
   - `POST /payment/create`, `GET /payment/status/{id}`, `POST /payment/webhook`, `GET /admin/payments`
   - Untuk tiap endpoint: method, URL, auth (public/user/admin), isi request body (input) dengan tipe, contoh request (curl), contoh response sukses, dan contoh response error (output).

Contoh template per endpoint:
````markdown
## POST /api/login

| | |
|---|---|
| Auth | Public |
| Body | `email` (string, required), `password` (string, required) |

Contoh request:
\`\`\`bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
\`\`\`

Contoh response (200):
\`\`\`json
{ "success": true, "message": "Login successful", "data": { "user": { "id": 1, "name": "Admin", "email": "admin@example.com", "role": "admin", ... }, "token": "1|..." } }
\`\`\`

Contoh response (401):
\`\`\`json
{ "success": false, "message": "Invalid credentials" }
\`\`\`
````
Isi `API.md` dengan contoh nyata dari verifikasi manual: output login, register, tabel, booking (termasuk 409 double booking), payment create (snap_token mock), webhook sukses, status expired, dsb. Pastikan tiap contoh konsisten dengan format envelope `{success, message, data|errors}`.

- [ ] **Step 2: Review & commit**

Baca ulang `API.md`: pastikan tidak ada endpoint yang terlewat, format konsisten, dan klaim di dokumen sesuai perilaku faktual kode. Lalu:
```bash
git add API.md
git commit -m "docs: add unified API documentation"
```

---

## Final Checklist (Verifikasi Seluruh Sistem)

Jalankan dari awal untuk memastikan whole system bekerja:

```bash
php artisan migrate:fresh --seed
php artisan serve   # terminal 1
php artisan schedule:work   # terminal 2
```

Lalu ikuti runbook:
```bash
# 1. login admin & user
# 2. admin buat tabel -> 201
# 3. user buat booking -> 201
# 4. user buat booking overlap -> 409
# 5. user create payment -> snap_token mock
# 6. webhook sukses (signature benar) -> booking paid
# 7. webhook signature salah -> 401
# 8. admin list bookings & payments -> 200
# 9. booking pending >15 menit -> expired (via job)
# 10. logout -> 200
```

Verifikasi hasil akhir di git: `git log --oneline` menampilkan 10 commit (Task 1-10).

## Self-Review Summary

- **Spec coverage:** Semua bagian spec ter-cakup: schema (Task 3), seeder (Task 4), auth (Task 5), admin middleware + CRUD tabel (Task 6), booking + anti double-booking 409 (Task 7), payment mock/midtrans + webhook idempotent (Task 8), auto-expire 15 menit (Task 9), API.md standar response (Task 10).
- **Placeholder scan:** Tidak ada TBD/TODO; semua kode lengkap per task.
- **Type consistency:** Nama helper `ApiResponse::success/error`, gateway method `getName/createTransaction/verifyWebhookSignature`, service method `createPayment`, dan relasi konsisten antar task (mis. `$request->user()->bookings()` di Task 8 membutuhkan relasi `bookings()` di User yang didefinisikan di task yang sama).