<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to CrypticX Lab</title>
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
        <h1 style="margin:18px 0 8px;color:#ffffff;font-size:27px;">
            Welcome to CrypticX Lab
        </h1>
        <p style="margin:0;color:#9ca3af;font-size:15px;">
            Your registration has been received.
        </p>
    </div>

    <p style="font-size:16px;line-height:1.7;">
        Hello {{ $userName }},
    </p>

    <p style="font-size:15px;line-height:1.8;color:#cbd5e1;">
        Your CrypticX Lab account has been created successfully.
        Your registration is currently
        <strong style="color:#fbbf24;">Pending Administrator Approval</strong>.
    </p>

    <div style="margin:24px 0;padding:18px;background:#0b0e14;border:1px solid #292f3b;border-radius:12px;">
        <strong style="color:#ffffff;">What happens next?</strong>
        <p style="margin:10px 0 0;color:#aeb6c3;font-size:14px;line-height:1.7;">
            An administrator will review your account. You will receive
            another email when your account has been approved and access
            has been granted.
        </p>
    </div>

    <p style="font-size:14px;line-height:1.7;color:#9ca3af;">
        CrypticX Lab is intended only for authorized cybersecurity
        testing, defensive security, education, and legitimate security
        research.
    </p>

    <p style="margin-top:28px;font-size:14px;color:#7f8794;">
        No action is required from you while your account is under review.
    </p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
