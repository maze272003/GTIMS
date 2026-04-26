# Unused Code Cleanup Report

## Removed files

1. `app/Http/Middleware/RateLimiter.php`
   - **Reason:** Unused/dead middleware implementation.
   - **Validation:** No middleware alias, route, or class reference pointed to this class; active middleware uses `App\Http\Middleware\RateLimitMiddleware`.

2. `config/ratelimit.php`
   - **Reason:** Unused duplicate rate-limit config.
   - **Validation:** No active code referenced `config('ratelimit.*')` after removing the dead `RateLimiter` middleware. Active implementation uses `config('rate_limit.*')` from `config/rate_limit.php`.

3. `app/View/Components/loader.php`
   - **Reason:** Unused/dead Blade component class.
   - **Validation:** No `<x-loader>` usage or class references found. It also rendered `components.loader`, which did not exist.

4. `app/View/Components/Admin/loader.php`
   - **Reason:** Unused/dead Blade component class.
   - **Validation:** No `<x-admin.loader>` usage or class references found.

5. `resources/views/components/admin/loader.blade.php`
   - **Reason:** Unused/dead Blade view.
   - **Validation:** Only referenced by the removed dead component class; no direct view includes/usages found.

6. `resources/views/index.blade.php`
   - **Reason:** Unused/dead view file.
   - **Validation:** No route/controller returned `view('index')`; root route points to `auth.login`.

## Removed code sections (non-file deletions)

1. `routes/web.php`
   - Removed obsolete commented-out `low-stock-settings` route block.
   - **Reason:** Dead/commented duplicate of active route group directly below it.

## Assumptions made

1. Runtime behavior is determined by actual route bindings, middleware aliases, component usage, and config lookups in the current codebase.
2. Unreferenced component/middleware files with no route/import/alias usage are safe to remove.
3. Removing obsolete commented code does not change runtime behavior.
