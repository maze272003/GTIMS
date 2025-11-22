<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Credentials</title>
    <style>
        /* Base Reset para sa Email Clients */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f5; margin: 0; padding: 0; }
        
        /* Main Wrapper */
        .email-wrapper { width: 100%; background-color: #f4f4f5; padding: 40px 0; }
        .email-content { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Header Blue Area */
        .header { background-color: #2563eb; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
        
        /* Body Content */
        .body-content { padding: 40px 30px; }
        .greeting { font-size: 18px; margin-bottom: 20px; color: #111; }
        .text-content { margin-bottom: 25px; color: #555; }
        
        /* Verify Button (Green) */
        .btn-container { text-align: center; margin: 35px 0; }
        .btn { display: inline-block; background-color: #16a34a; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background-color 0.3s; }
        .btn:hover { background-color: #15803d; }
        
        /* Credentials Box (Gray) */
        .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 25px; }
        .info-row { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .info-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        
        .label { font-weight: 600; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .value { font-weight: 700; color: #1e293b; font-size: 15px; font-family: 'Courier New', monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
        
        /* Warning Alert (Red) */
        .alert { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin-top: 25px; border-radius: 4px; }
        .alert-text { color: #b91c1c; font-size: 13px; margin: 0; }
        
        /* Footer */
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { font-size: 12px; color: #94a3b8; margin: 0; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
            
            <div class="header">
                <h1>Action Required</h1>
            </div>
            
            <div class="body-content">
                <p class="greeting">Hello <strong>{{ $user->name }}</strong>,</p>
                
                <p class="text-content">
                    An account has been created for you by the Administrator. Before you can access the system, 
                    <strong>you must verify your email address</strong> to activate your account.
                </p>

                <div class="btn-container">
                    <a href="{{ $verificationUrl }}" class="btn">Verify Account Now</a>
                </div>

                <p class="text-content">Here are your official login credentials:</p>

                <div class="info-box">
                    <div class="info-row">
                        <span class="label">Email</span>
                        <span class="value" style="background: none;">{{ $user->email }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Password</span>
                        <span class="value">{{ $rawPassword }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Role</span>
                        <span class="value" style="background: none;">{{ ucfirst($user->level->name ?? 'N/A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Branch</span>
                        <span class="value" style="background: none;">{{ $user->branch->name ?? 'Head Office' }}</span>
                    </div>
                </div>

                <div class="alert">
                    <p class="alert-text">
                        <strong>Important:</strong> You cannot log in until you click the "Verify Account Now" button above. 
                        Please change your password immediately after your first login.
                    </p>
                </div>
            </div>

            <div class="footer">
                <p class="footer-text">
                    This is an automated message. Please do not reply to this email.<br>
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>