<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Store Registration</title>
  <style>
    /* Reset */
    body,table,td{margin:0;padding:0;border:0;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial}
    img{border:0;display:block}
    a{color:inherit;text-decoration:none}
    .email-wrap{width:100%;background:#b18b5e; padding:32px 0}
    .email-body{width:90%;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #ececec}
    .email-header{padding:28px 32px;background:linear-gradient(90deg, rgba(177,139,94,0.08), rgba(177,139,94,0.02));display:flex;align-items:center;gap:12px}
    .brand{height:44px;width:auto}
    .email-content{padding:28px 32px;color:#333}
    .lead{font-size:18px;margin:0 0 12px 0;color:#111;font-weight:600}
    .muted{color:#6b6b6b;font-size:13px}
    .code-box{margin:18px 0;padding:18px;border-radius:8px;background:linear-gradient(180deg, #fffef8, #fffaf0);border:1px solid rgba(177,139,94,0.12);display:flex;align-items:center;justify-content:center}
    .code{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, 'Roboto Mono', monospace;font-size:22px;letter-spacing:2px;color:#b18b5e;font-weight:700}
    .cta{display:inline-block;padding:10px 18px;background:#b18b5e;color:#fff;border-radius:8px;margin-top:8px}
    .hint{font-size:12px;color:#8a8a8a;margin-top:12px}
    .email-footer{padding:18px 32px;background:#fafafa;border-top:1px solid #f0f0f0;color:#7a7a7a;font-size:13px}
    @media (max-width:420px){.email-body{border-radius:6px}.email-header{padding:18px}.email-content{padding:18px}}
  </style>
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header">
        <img class="brand" src="{{ asset('imgs/logo/logo.png') }}" alt="logo">
        <div>
          <div style="font-weight:700;color:#222">BlessedStore</div>
          <div style="font-size:12px;color:#8b8b8b">Store Registration</div>
        </div>
      </div>

      <div class="email-content">
        <p class="lead">Hi {{ $name }}, your store, {{ $store_name }} has been registered successfully</p>
        <p class="muted">You can log into your account using your email address and your password below</p>

        <div class="code-box" role="status" aria-label="Verification code">
          <div class="code">{{ $password }}</div>
        </div>

        <p class="hint">You are advised to change your password to a stronger one after you log in.</p>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
