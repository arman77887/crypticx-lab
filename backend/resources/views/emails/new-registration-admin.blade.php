<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New CrypticX Lab Registration</title>
</head>
<body style="margin:0;padding:0;background:#090b10;font-family:Arial,Helvetica,sans-serif;color:#e5e7eb;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#090b10;padding:32px 12px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#11141b;border:1px solid #252a35;border-radius:18px;">
<tr>
<td style="padding:36px;">
    <div style="text-align:center;margin-bottom:28px;">
        <img src="{{ $logoUrl }}" width="72" height="72" alt="CrypticX Lab" style="display:inline-block;max-width:72px;">
        <h1 style="margin:18px 0 8px;color:#ffffff;font-size:25px;">
            New Account Awaiting Approval
        </h1>
    </div>

    <p style="font-size:15px;line-height:1.7;color:#cbd5e1;">
        A new user has registered for CrypticX Lab and is waiting for
        administrator approval.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;background:#0b0e14;border:1px solid #292f3b;border-radius:12px;">
        <tr>
            <td style="padding:18px;font-size:14px;line-height:2;color:#aeb6c3;">
                <strong style="color:#ffffff;">Name:</strong>
                {{ $userName }}<br>
                <strong style="color:#ffffff;">Email:</strong>
                {{ $userEmail }}<br>
                <strong style="color:#ffffff;">Registered:</strong>
                {{ $registeredAt ?? 'Recently' }}<br>
                <strong style="color:#ffffff;">Status:</strong>
                Pending Administrator Approval
            </td>
        </tr>
    </table>

    <div style="text-align:center;margin-top:28px;">
        <a href="{{ $adminUrl }}"
           style="display:inline-block;padding:13px 22px;background:#ffffff;color:#090b10;text-decoration:none;font-weight:bold;border-radius:10px;">
            Review Account
        </a>
    </div>

    <p style="margin-top:28px;font-size:12px;line-height:1.6;color:#707784;">
        This administrative notification does not contain the user's
        password or authentication credentials.
    </p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
