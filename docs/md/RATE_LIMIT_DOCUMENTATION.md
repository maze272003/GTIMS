# Rate Limiting System Documentation

## Overview

This document describes the rate limiting system implemented for the GTIMS application. The system uses a sliding window algorithm with Redis for distributed rate limiting.

## Architecture

### Components

1. **RateLimitService** (`app/Services/RateLimitService.php`)
   - Core service handling rate limit logic
   - Supports Redis and local cache drivers
   - Implements sliding window algorithm

2. **RateLimitMiddleware** (`app/Http/Middleware/RateLimitMiddleware.php`)
   - Laravel middleware for applying rate limits to routes
   - Adds rate limit headers to responses

3. **Configuration** (`config/rate_limit.php`)
   - Centralized configuration for all rate limit settings

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `RATE_LIMIT_ENABLED` | `true` | Enable/disable rate limiting |
| `RATE_LIMIT_DRIVER` | `redis` | Storage driver (redis/cache) |
| `RATE_LIMIT_DEBUG` | `false` | Show detailed error pages |
| `RATE_LIMIT_USE_IP` | `true` | Use IP for identification |
| `RATE_LIMIT_USE_USER_ID` | `true` | Use user ID when authenticated |

### Rate Limit Groups

| Group | Max Requests | Decay (seconds) | Description |
|-------|--------------|-----------------|-------------|
| `auth` | 5 | 60 | Login, register, password reset |
| `otp` | 5 | 60 | OTP send and verify |
| `api` | 60 | 60 | General API endpoints |
| `admin` | 120 | 60 | Admin panel endpoints |
| `public` | 30 | 60 | Public pages and search |
| `export` | 10 | 300 | Export operations (PDF, Excel) |

## Usage

### Applying Rate Limits to Routes

```php
// Strict limit for auth routes
Route::post('login', [AuthController::class, 'store'])
    ->middleware('rate.limit:auth');

// Moderate limit for API routes
Route::get('/api/data', [DataController::class, 'index'])
    ->middleware('rate.limit:api');

// Stricter limit for exports
Route::get('/export/pdf', [ExportController::class, 'pdf'])
    ->middleware('rate.limit:export');
```

### Current Route Bindings

The system is applied to:

1. **Auth Routes** (`routes/auth.php`):
   - `POST /register` - auth
   - `POST /login` - auth
   - `POST /forgot-password` - auth
   - `POST /reset-password` - auth
   - `POST /email/verification-notification` - auth
   - `GET /verify-email/{id}/{hash}` - auth

2. **OTP Routes** (`routes/web.php`):
   - `POST /send-otp` - otp
   - `POST /verify-otp` - otp

3. **Admin Routes** (`routes/web.php`):
   - All routes under `/admin/*` - admin

4. **Export Routes** (`routes/web.php`):
   - `GET /admin/patientrecords/export-pdf` - export
   - `GET /admin/patientrecords/export-excel` - export
   - `POST /admin/inventory/export` - export
   - `GET /admin/suppliers/export-excel` - export

## Response Headers

When rate limiting is active, the following headers are added:

- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in window
- `Retry-After`: Seconds until retry (when blocked)

## Rate Limited Response

When a request is blocked:

### JSON Response
```json
{
    "message": "Too many requests. Please try again later.",
    "rate_limit": {
        "group": "auth",
        "limit": 5,
        "remaining": 0,
        "retry_after": 45
    }
}
```

### HTTP Status Code
- `429 Too Many Requests`

## Debug Mode

Enable debug mode to see detailed error pages:

```env
RATE_LIMIT_DEBUG=true
```

## Disabling Rate Limiting

### For Development

```env
RATE_LIMIT_ENABLED=false
```

### For Specific Routes

The middleware checks if rate limiting is disabled via the config. When disabled, all requests pass through without limits.

## Logging

Rate limit events are logged to the `daily` log channel:

```php
Log::channel('daily')->warning('Rate limit exceeded', [
    'key' => 'auth:ip:192.168.1.1',
    'attempts' => 5,
    'limit' => 5,
    'retry_after' => 45,
]);
```

## Customization

### Adding Custom Rate Limit Groups

Edit `config/rate_limit.php`:

```php
'limits' => [
    'custom' => [
        'max_requests' => 100,
        'decay_seconds' => 60,
        'description' => 'Custom endpoint group',
    ],
],
```

### Using Different Identifier Types

```php
// Use only IP
config(['rate_limit.identifiers.use_user_id' => false]);

// Use only user ID
config(['rate_limit.identifiers.use_ip' => false]);
```

## Testing

Run the rate limit tests:

```bash
php artisan test --filter=RateLimit
```

### Test Coverage

- Request blocking after limit exceeded
- Rate limit headers present
- Proper 429 response code
- Different limits per route group
- Retry-After calculation

## Security Considerations

1. **IP Detection**: Properly handles `X-Forwarded-For` headers for proxy environments
2. **User Identification**: Uses both IP and user ID when available
3. **Fallback**: Always has a fallback identifier even if both IP and user ID fail
4. **Brute Force Protection**: Strict limits on auth endpoints prevent brute force attacks

## Troubleshooting

### Redis Connection Issues

If Redis is unavailable, the system falls back gracefully:
- Logs the error
- Allows all requests in debug mode
- In production, blocks requests to prevent abuse

### Cache Driver Alternative

For development without Redis, switch to cache driver:

```env
RATE_LIMIT_DRIVER=cache
```