<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: Arial, Helvetica, sans-serif; line-height: 1.6;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #e2e8f0; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <tr>
                        <td align="center" style="padding: 30px 20px 25px 20px; background-color: #dc2626; color: #ffffff;"> <img src="{{ asset('images/gtlogo.png') }}" alt="Logo" width="90" style="display: block; margin: 0 auto;">
                            <h1 style="margin: 10px 0 0 0; font-size: 22px; font-weight: 600;">Security Alert</h1>
                            <p style="margin: 5px 0 0 0; font-size: 16px;">Inventory Management System</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px 40px 30px; color: #333333;">
                            <h2 style="font-size: 24px; margin: 0 0 20px 0; color: #111827; font-weight: 600;">New Login Detected</h2>
                            <p style="margin-bottom: 25px; font-size: 16px;">
                                Your account was just used to log in from a new device or IP address.
                            </p>
                            <p style="font-size: 16px;"><b>Time:</b> {{ now()->format('F j, Y, g:i a') }} (PHT)</p>
                            <p style="font-size: 16px;"><b>IP Address:</b> {{ $ipAddress }}</p>
                            <br>
                            <p style="font-size: 16px;">
                                <b>If this was you,</b> you can safely ignore this email.
                            </p>
                            <p style="font-size: 16px;">
                                <b>If this was NOT you,</b> please secure your account immediately by resetting your password.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px 30px; background-color: #f8fafc; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0;">&copy; {{ date('Y') }} Municipality of General Tinio. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>