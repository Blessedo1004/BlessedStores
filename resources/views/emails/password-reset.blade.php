<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset your password</title>
  @include('emails.partials.styles')
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header">
        <img class="brand" src="{{ asset('imgs/logo/logo.png') }}" alt="logo">
        <div>
          <div style="font-weight:700;color:#222">BlessedStores</div>
          <div style="font-size:12px;color:#8b8b8b">Password Reset</div>
        </div>
      </div>

      <div class="email-content">
        <p class="muted">The password for your account has been reset successfully.</p>
        <p class="muted">Time of Change: {{ now()->format('M d, Y, g:i A') }} (WAT)</p>
        <hr style="border:none;border-top:1px solid #f0f0f0;margin:18px 0">

        <p class="muted" style="margin:0">If you did not request this, contact our support team immediately.</p>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
