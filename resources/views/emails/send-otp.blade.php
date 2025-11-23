<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification & Credentials</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: Arial, Helvetica, sans-serif; line-height: 1.6;">
    
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0;">
                
                {{-- Main Container --}}
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #e2e8f0; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    
                    {{-- HEADER (Red Background) --}}
                    <tr>
                        <td align="center" style="padding: 30px 20px 25px 20px; background-color: #eb0000; color: #ffffff;">
                            
                            {{-- Make sure this image path is correct or accessible via public URL --}}
                            {{-- Kapag nasa email, mas maganda kung absolute URL (e.g., https://yoursite.com/images/gtlogo.png) --}}
                            <img src="{{ asset('images/gtlogo.png') }}" alt="Municipality of General Tinio Logo" width="90" style="display: block; margin: 0 auto;">
                            
                            <h1 style="margin: 10px 0 0 0; font-size: 22px; font-weight: 600;">Municipality of General Tinio</h1>
                            <p style="margin: 5px 0 0 0; font-size: 16px;">Inventory Management System</p>
                        </td>
                    </tr>
                    
                    {{-- BODY CONTENT --}}
                    <tr>
                        <td style="padding: 40px 30px 40px 30px; color: #333333;">
                            <h2 style="font-size: 24px; margin: 0 0 20px 0; color: #111827; font-weight: 600;">Welcome, {{ $user->name }}!</h2>
                            <p style="margin-bottom: 20px; font-size: 16px;">
                                An account has been created for you. Before you can login, you need to verify your email address by clicking the button below.
                            </p>
                            
                            {{-- VERIFY BUTTON --}}
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 25px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $verificationUrl }}" style="background-color: #1d4ed8; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;">
                                            Verify Account Now
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin-bottom: 15px; font-size: 16px;">
                                Here are your temporary login credentials:
                            </p>

                            {{-- CREDENTIALS BOX (Gray Box Style similar to OTP) --}}
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <div style="background-color: #f3f4f6; border-radius: 8px; padding: 20px 30px; display: block; border: 1px solid #e5e7eb; text-align: left;">
                                            
                                            <table border="0" cellpadding="5" cellspacing="0" width="100%">
                                                <tr>
                                                    <td style="font-size: 14px; color: #64748b; font-weight: bold; width: 80px;">EMAIL:</td>
                                                    <td style="font-size: 16px; color: #111827; font-weight: 600;">{{ $user->email }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 14px; color: #64748b; font-weight: bold;">PASS:</td>
                                                    <td style="font-size: 16px; color: #1d4ed8; font-weight: bold; font-family: monospace;">{{ $rawPassword }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 14px; color: #64748b; font-weight: bold;">ROLE:</td>
                                                    <td style="font-size: 16px; color: #111827;">{{ ucfirst($user->level->name ?? 'N/A') }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 14px; color: #64748b; font-weight: bold;">BRANCH:</td>
                                                    <td style="font-size: 16px; color: #111827;">{{ $user->branch->name ?? 'Head Office' }}</td>
                                                </tr>
                                            </table>

                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin-top: 25px; font-size: 14px; color: #dc2626;">
                                <strong>Important:</strong> You cannot login until you verify your account. Please change your password immediately after your first login.
                            </p>
                        </td>
                    </tr>
                    
                    {{-- FOOTER --}}
                    <tr>
                        <td style="padding: 30px 30px; background-color: #f8fafc; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0;">&copy; {{ date('Y') }} Municipality of General Tinio. All rights reserved.</p>
                            <p style="margin: 5px 0 0 0;">General Tinio, Nueva Ecija</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
    
</body>
</html>