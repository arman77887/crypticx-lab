<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approved</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#172033;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" style="padding:32px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"
       style="max-width:600px;background:#ffffff;border-radius:16px;">
<tr>
<td style="padding:36px;">
    <h1 style="margin:0 0 20px;font-size:26px;">
        Welcome to CrypticX Lab
    </h1>

    <p style="font-size:16px;line-height:1.7;">
        Hello {{ $user->name }},
    </p>

    <p style="font-size:16px;line-height:1.7;">
        Your CrypticX Lab account has been successfully created,
        verified, and approved.
    </p>

    <p style="font-size:16px;line-height:1.7;">
        You can now sign in using your registered email address
        and password.
    </p>

    <div style="margin:24px 0;padding:18px;background:#f4f6f8;border-radius:12px;">
        <strong>Device security enabled</strong>
        <p style="margin:8px 0 0;font-size:14px;line-height:1.6;">
            The browser/device used during registration has been
            registered for your account. Sign-in attempts from an
            unregistered device will be rejected.
        </p>
    </div>

    <p style="font-size:14px;line-height:1.7;color:#5f6b7a;">
        If you did not create this account, please contact
        CrypticX Lab support.
    </p>

    <p style="margin-top:28px;font-size:14px;color:#5f6b7a;">
        CrypticX Lab Security
    </p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
