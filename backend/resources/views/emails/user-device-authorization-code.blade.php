<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify New Device</title>
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
        Verify your new device
    </h1>

    <p style="font-size:16px;line-height:1.7;">
        Hello {{ $user->name }},
    </p>

    <p style="font-size:16px;line-height:1.7;">
        A sign-in attempt was made from a device that is not yet
        authorized for your CrypticX Lab account.
    </p>

    <p style="font-size:15px;line-height:1.6;">
        Enter this verification code to authorize the device:
    </p>

    <div style="margin:24px 0;padding:20px;text-align:center;background:#f4f6f8;border-radius:12px;">
        <strong style="font-size:32px;letter-spacing:8px;">
            {{ $code }}
        </strong>
    </div>

    <p style="font-size:14px;line-height:1.7;color:#5f6b7a;">
        This code expires shortly and can only be used for this
        device authorization request. Never share this code.
    </p>

    <p style="font-size:14px;line-height:1.7;color:#5f6b7a;">
        If this was not you, do not enter the code. Your device
        will not be authorized.
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
