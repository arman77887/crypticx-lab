<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CrypticX Lab Password Reset</title>
</head>
<body style="margin:0;padding:0;background:#0b0f14;font-family:Arial,sans-serif;color:#e8eef7;">
    <div style="max-width:560px;margin:0 auto;padding:40px 20px;">
        <div style="background:#111821;border:1px solid #263241;border-radius:18px;padding:32px;">
            <h1 style="margin:0 0 16px;font-size:24px;">
                Password reset verification
            </h1>

            <p style="color:#aab7c6;line-height:1.6;">
                Use this verification code to continue resetting your
                CrypticX Lab password.
            </p>

            <div style="margin:28px 0;padding:20px;text-align:center;background:#0b1118;border-radius:14px;font-size:34px;font-weight:700;letter-spacing:8px;">
                {{ $code }}
            </div>

            <p style="color:#aab7c6;line-height:1.6;">
                This code expires in 10 minutes. Do not share it with anyone.
            </p>

            <p style="margin-bottom:0;color:#718096;font-size:13px;line-height:1.6;">
                If you did not request a password reset, you can ignore this email.
            </p>
        </div>
    </div>
</body>
</html>
