<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Requests - Rate Limit Exceeded</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: #f97316;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 72px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 18px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .message {
            color: #374151;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6b7280;
            font-weight: 500;
        }
        .info-value {
            color: #111827;
            font-weight: 600;
        }
        .retry-timer {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .retry-timer h3 {
            color: #92400e;
            margin-bottom: 10px;
        }
        .timer {
            font-size: 32px;
            font-weight: bold;
            color: #d97706;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>429</h1>
            <p>Too Many Requests</p>
        </div>
        <div class="content">
            <div class="message">
                {{ $message ?? 'You have made too many requests. Please wait before trying again.' }}
            </div>
            
            @if(isset($retry_after) && $retry_after > 0)
            <div class="retry-timer">
                <h3>Please wait</h3>
                <div class="timer" id="timer">{{ $retry_after }}</div>
                <p>seconds remaining</p>
            </div>
            @endif

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Rate Limit</span>
                    <span class="info-value">{{ $limit ?? 'N/A' }} requests/min</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Group</span>
                    <span class="info-value">{{ $group ?? 'api' }}</span>
                </div>
                @if(isset($retry_after) && $retry_after > 0)
                <div class="info-row">
                    <span class="info-label">Retry After</span>
                    <span class="info-value">{{ $retry_after }} seconds</span>
                </div>
                @endif
            </div>

            <div style="text-align: center;">
                <a href="{{ url('/') }}" class="btn">Go to Home</a>
            </div>
        </div>
    </div>

    @if(isset($retry_after) && $retry_after > 0)
    <script>
        let seconds = {{ $retry_after }};
        const timerElement = document.getElementById('timer');
        
        const countdown = setInterval(function() {
            seconds--;
            timerElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.reload();
            }
        }, 1000);
    </script>
    @endif
</body>
</html>