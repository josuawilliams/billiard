# 🎱 Billiard Booking System — AI Instruction File

## 📌 Project Overview
Build a **Billiard Booking System** using **Laravel (API-based)** with **Payment Gateway integration (Midtrans/Xendit)**.

The system allows users to:
- View available billiard tables
- Book tables by date and time
- Pay online
- Track booking status

Admin can:
- Manage tables
- Monitor bookings
- View transactions

---

## 🧱 Tech Stack
- Backend: Laravel (latest)
- Auth: Laravel Sanctum / JWT
- Database: MySQL
- Payment Gateway: Midtrans (Snap API)
- Queue: Laravel Queue (for webhook & auto-expire)
- Storage: Local / S3 (optional)

---

## 🗄️ Database Schema

### users
- id (PK)
- name
- email (unique)
- password
- created_at
- updated_at

### tables
- id (PK)
- name
- price_per_hour (decimal)
- created_at
- updated_at

### bookings
- id (PK)
- user_id (FK → users.id)
- table_id (FK → tables.id)
- booking_date (date)
- start_time (time)
- end_time (time)
- total_price (decimal)
- status (enum: pending, paid, cancelled, expired)
- created_at
- updated_at

### payments
- id (PK)
- booking_id (FK → bookings.id)
- payment_gateway (string)
- transaction_id (string)
- amount (decimal)
- status (enum: pending, success, failed)
- paid_at (timestamp)
- created_at

---

## 🔗 Relationships
- User has many Bookings
- Table has many Bookings
- Booking has one Payment

---

## ⚙️ Core Features

### 1. Authentication
- Register
- Login
- Logout

---

### 2. Table Management (Admin)
- Create table
- Update table
- Delete table
- List tables

---

### 3. Booking System
- User selects:
  - date
  - start_time
  - duration
- System calculates:
  - end_time
  - total_price

---

### 4. Anti Double Booking Logic (IMPORTANT)

Reject booking if overlap exists:

```
WHERE table_id = ?
AND booking_date = ?
AND (
  start_time < requested_end
  AND end_time > requested_start
)
```

---

### 5. Booking Flow
1. Create booking → status `pending`
2. Generate payment
3. Redirect to payment page
4. Wait for webhook
5. Update booking status → `paid`

---

### 6. Payment Integration (Midtrans)

#### Create Payment
- Endpoint: `/api/payment/create`
- Generate Snap transaction
- Save transaction_id

#### Webhook Handler
- Endpoint: `/api/payment/webhook`
- Validate signature
- Update:
  - payment.status → success/failed
  - booking.status → paid

---

### 7. Auto Expire Booking
- If not paid within 15 minutes:
  - booking.status → expired

Use:
- Laravel Scheduler
- Queue Job

---

## 📡 API Endpoints

### Auth
- POST `/api/register`
- POST `/api/login`
- POST `/api/logout`

### Tables
- GET `/api/tables`
- POST `/api/tables` (admin)
- PUT `/api/tables/{id}` (admin)
- DELETE `/api/tables/{id}` (admin)

### Bookings
- POST `/api/bookings`
- GET `/api/bookings`
- GET `/api/bookings/{id}`

### Payments
- POST `/api/payment/create`
- POST `/api/payment/webhook`

---

## 📁 Suggested Folder Structure

```
app/
 ├── Models/
 │    ├── User.php
 │    ├── Table.php
 │    ├── Booking.php
 │    └── Payment.php
 │
 ├── Http/Controllers/
 │    ├── AuthController.php
 │    ├── TableController.php
 │    ├── BookingController.php
 │    ├── PaymentController.php
 │
 ├── Services/
 │    └── PaymentService.php
 │
 ├── Jobs/
 │    └── ExpireBookingJob.php
```

---

## 🔐 Security Considerations
- Validate all inputs
- Use transactions for booking + payment
- Verify webhook signature
- Prevent duplicate payment updates

---

## 🚀 Advanced Features (Optional)
- Email notification after payment
- QR code check-in
- Promo / discount system
- Multi-branch support
- Real-time availability (WebSocket)

---

## 🧠 Expected AI Behavior
When generating code:
- Follow Laravel best practices
- Use clean architecture (Controller → Service → Model)
- Use Form Request validation
- Use Eloquent ORM
- Keep logic separated (no fat controllers)

---

## 🎯 Goal
Produce a **production-ready backend system** that demonstrates:
- Payment integration
- Booking logic
- Data consistency
- Scalable architecture

---