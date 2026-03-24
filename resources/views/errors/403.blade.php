<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --danger: #b91c1c;
            --danger-soft: #fee2e2;
            --border: #e5e7eb;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: radial-gradient(circle at top, #eef2ff 0%, var(--bg) 45%, #eef2f7 100%);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
        }

        .card {
            width: min(680px, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--danger-soft);
            color: var(--danger);
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.02em;
        }

        h1 {
            margin: 16px 0 10px;
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1.15;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: #fff;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-secondary:hover { background: #f9fafb; }

        .detail {
            margin-top: 14px;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <x-global-preloader />

    @php
        $authSessionService = app(\App\Services\AuthSessionService::class);
        $destination = auth()->check() ? $authSessionService->getRedirectDestination(auth()->user()) : null;
        $message = trim((string) ($exception?->getMessage() ?? ''));
        $fallbackMessage = $authSessionService->getForbiddenMessage(auth()->user());
        $currentUrl = url()->current();
        $previousUrl = url()->previous();
        $safeBackUrl = $previousUrl !== $currentUrl ? $previousUrl : url('/');
        $primaryUrl = $destination['url'] ?? $safeBackUrl;
        $primaryLabel = $destination ? 'Go to '.$destination['label'] : 'Go Back';
    @endphp
    <main class="card" role="main" aria-labelledby="error-title">
        <div class="badge">403 Forbidden</div>
        <h1 id="error-title">This page cannot be accessed</h1>
        <p>
            {{ $message !== '' ? $message : $fallbackMessage }}
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
            <a class="btn btn-secondary" href="{{ $safeBackUrl }}">Go Back</a>
        </div>

        <p class="detail">If you need access, contact the superadmin so your permissions can be reviewed.</p>
    </main>
</body>
</html>
