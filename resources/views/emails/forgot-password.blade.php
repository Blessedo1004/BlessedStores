<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verify your email</title>
  @include('emails.partials.styles')
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header">
        <img class="brand" src="{{ asset('imgs/logo/logo.png') }}" alt="logo">
        <div>
          <div style="font-weight:700;color:#222">BlessedStores</div>
          <div style="font-size:12px;color:#8b8b8b">Reset your password</div>
        </div>
      </div>

      <div class="email-content">
        <p class="muted">To reset your password, please verify your email address using the code below. This code expires in 15 minutes.</p>

        <div class="code-box" role="status" aria-label="Verification code">
          <div class="code">{{ $code }}</div>
        </div>

        <p class="hint">Copy and paste the code above on the verification page.</p>

        <hr style="border:none;border-top:1px solid #f0f0f0;margin:18px 0">

        <p class="muted" style="margin:0">If you did not request this, you can safely ignore this email — no action is required.</p>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
