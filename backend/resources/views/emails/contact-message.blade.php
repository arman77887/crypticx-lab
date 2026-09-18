<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CrypticX Lab Contact Message</title>
</head>
<body style="margin:0;background:#090b0f;color:#e5e7eb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#090b0f;padding:32px 12px;">
<tr>
<td align="center">

<table width="100%" cellpadding="0" cellspacing="0"
       style="max-width:680px;background:#11151b;border:1px solid #2a3039;border-radius:20px;overflow:hidden;">

<tr>
<td style="padding:28px 30px;background:#151a21;border-bottom:1px solid #2a3039;">
    <div style="font-size:12px;letter-spacing:3px;color:#ef4444;font-weight:bold;">
        CRYPTICX LAB
    </div>

    <h1 style="margin:10px 0 0;font-size:25px;color:#ffffff;">
        New Contact Message
    </h1>
</td>
</tr>

<tr>
<td style="padding:30px;">

    <p style="margin:0 0 22px;color:#9ca3af;font-size:14px;">
        A visitor submitted the secure contact form.
    </p>

    <table width="100%" cellpadding="8" cellspacing="0"
           style="font-size:14px;border-collapse:collapse;">

        <tr>
            <td style="color:#9ca3af;width:120px;">Name</td>
            <td style="color:#ffffff;font-weight:bold;">
                {{ $contactData['name'] }}
            </td>
        </tr>

        <tr>
            <td style="color:#9ca3af;">Email</td>
            <td style="color:#ffffff;">
                {{ $contactData['email'] }}
            </td>
        </tr>

        <tr>
            <td style="color:#9ca3af;">Category</td>
            <td style="color:#ffffff;">
                {{ ucfirst($contactData['category']) }}
            </td>
        </tr>

        <tr>
            <td style="color:#9ca3af;">Subject</td>
            <td style="color:#ffffff;">
                {{ $contactData['subject'] }}
            </td>
        </tr>

    </table>

    <div style="margin-top:24px;padding:20px;background:#0b0e13;border:1px solid #292f38;border-radius:14px;">
        <div style="margin-bottom:10px;color:#ef4444;font-size:11px;font-weight:bold;letter-spacing:2px;">
            MESSAGE
        </div>

        <div style="color:#e5e7eb;font-size:15px;line-height:1.7;white-space:pre-wrap;">{{ $contactData['message'] }}</div>
    </div>

    <p style="margin:24px 0 0;color:#6b7280;font-size:12px;line-height:1.6;">
        Reply to this email normally. The Reply-To address is set to the visitor's submitted email address.
    </p>

</td>
</tr>
</table>

</td>
</tr>
</table>
</body>
</html>
