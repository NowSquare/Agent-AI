{{-- Agent AI Login Code --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent AI Login Code</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
        .code { font-size: 32px; font-weight: bold; color: #007bff; text-align: center; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        .footer { margin-top: 30px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Agent AI</h1>
            <p>Your login code</p>
        </div>

        <p>Hello!</p>

        <p>Use this code to sign in to Agent AI:</p>

        <div class="code">{{ $code }}</div>

        <p>Or click the link below to sign in automatically:</p>

        <p style="text-align: center;">
            <a href="{{ $magicLink }}" class="button">Sign In to Agent AI</a>
        </p>

        <p>This code will expire at {{ $expiresAt->format('g:i A T') }}.</p>

        <div class="footer">
            <p>If you didn't request this code, you can safely ignore this email.</p>
            <p>Questions? Contact support at <a href="mailto:support@example.com">support@example.com</a></p>
        </div>
    </div>
</body>
</html>
