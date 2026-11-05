<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Store Status</title>
  @include('emails.partials.styles')
</head>
<body>
  <div class="email-wrap">
    <div class="email-body">
      <div class="email-header">
        <img class="brand" src="{{ asset('imgs/logo/logo.png') }}" alt="logo">
        <div>
          <div style="font-weight:700;color:#222">BlessedStores</div>
          <div style="font-size:12px;color:#8b8b8b">Store Status</div>
        </div>
      </div>

      <div class="email-content">
        <p class="lead">Hi {{ $owner_name }}, your store, {{ $store_name }} has been {{ $status === 'active' ? 'restored'  : $status }}.</p>
        @if($suspension_reason)
            <p class="lead">The reason for this suspension is : {{$suspension_reason}}</p>
        @endif
        <p class="lead">Thank you for choosing BlessedStores!</p>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
