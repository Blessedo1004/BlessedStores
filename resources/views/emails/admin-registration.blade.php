<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Store Registration</title>
  @include('emails.partials.styles')
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header">
        <img class="brand" src="{{ asset('imgs/logo/logo.png') }}" alt="logo">
        <div>
          <div style="font-weight:700;color:#222">BlessedStores</div>
          <div style="font-size:12px;color:#8b8b8b">Admin Registration</div>
        </div>
      </div>

      <div class="email-content">
        <p class="lead">Hi {{ $name }}, your admin account has been registered successfully</p>
        <p class="lead">You can log into your account using your email address and your password below</p>

        <div class="code-box" role="status" aria-label="Verification code">
          <div class="code">{{ $password }}</div>
        </div>

        <p class="lead">You are advised to change your password to a stronger one after you log in. Do not share it with anyone.</p>
        <p class="lead">Thank you for choosing BlessedStores!</p>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
