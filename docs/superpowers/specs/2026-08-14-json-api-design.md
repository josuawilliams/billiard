# Design: Pure JSON API Response

Date: 2026-08-14

## Context

The project is a Laravel 12 backend intended to serve a separate (non-monolith) frontend. All `/api/*` routes already return JSON via `App\Support\ApiResponse`. Remaining HTML surfaces are the root web route (`/` returning `view('welcome')`) and Laravel's default HTML error pages (404/500/unauthorized).

Goal: make the backend return JSON for every request, including root and error responses.

## Changes

### 1. `routes/web.php`

Replace the `/` route returning `view('welcome')` with a JSON response:

```php
Route::get('/', fn () => ApiResponse::success(null, 'Billiard API'));
```

Delete `resources/views/welcome.blade.php` (no longer referenced).

### 2. `bootstrap/app.php`

Configure the exception handler to always render JSON, regardless of `Accept` header. Use `$exceptions->shouldRenderJsonWhen(...)` returning `true` so that:

- 404 (`NotFoundHttpException`) -> `ApiResponse::error('Not Found', 404)`
- 401/403 unauthorized -> JSON error
- 500 -> JSON error

### 3. `app/Support/ApiResponse.php`

No structural change required; existing `success()` and `error()` helpers cover the cases above.

## Result

Every request to the backend returns JSON with shape `{"success": true|false, "message": "...", "data": ...}`. Suitable for Postman testing and a decoupled frontend.

## Non-goals

- No new endpoints added.
- No changes to existing `/api` route logic or controllers.
- No auth/rate-limiting changes.
