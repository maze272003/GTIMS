<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Invitation</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background: #f8fafc; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0;">
        <tr>
            <td style="padding: 24px;">
                <h2 style="margin: 0 0 12px;">You have been invited to GTIMS</h2>
                <p style="margin: 0 0 10px;">
                    Province: <strong>{{ $invitation->province?->name ?? ('#' . $invitation->province_id) }}</strong>
                </p>
                @if($invitation->barangay)
                    <p style="margin: 0 0 10px;">
                        Barangay: <strong>{{ $invitation->barangay->barangay_name }}</strong>
                    </p>
                @endif
                <p style="margin: 0 0 10px;">
                    Role: <strong>{{ $invitation->role?->name ?? ('#' . $invitation->role_id) }}</strong>
                </p>
                <p style="margin: 0 0 20px;">
                    This invitation expires on <strong>{{ optional($invitation->expires_at)->format('F j, Y g:i A') }}</strong>.
                </p>

                <p style="margin: 0 0 20px;">
                    <a href="{{ $invitationUrl }}" style="display: inline-block; background: #b91c1c; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px;">Accept Invitation</a>
                </p>

                <p style="margin: 0; color: #475569; font-size: 12px;">
                    If the button does not work, copy this URL:
                </p>
                <p style="margin: 4px 0 0; color: #1d4ed8; word-break: break-word; font-size: 12px;">
                    {{ $invitationUrl }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

