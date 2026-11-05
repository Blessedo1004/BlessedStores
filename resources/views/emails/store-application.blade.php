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
          <div style="font-size:12px;color:#8b8b8b">Store Registration</div>
        </div>
      </div>

      <div class="email-content">
        <p class="lead">Hi {{ $storeApplicationData->owner_name }}, your application has been submitted successfully. We will review it and get back to you within 7 business days.</p>

        <p>Below are the details of your application:</p>
        <ul>
          <li><strong>Store Name:</strong> {{ $storeApplicationData->store_name }}</li>
          <li><strong>Owner Name:</strong> {{ $storeApplicationData->owner_name }}</li>
          <li><strong>Email:</strong> {{ $storeApplicationData->email }}</li>
          <li><strong>Phone Number:</strong> {{ $storeApplicationData->phone_number }}</li>
          <li><strong>Description:</strong> {{ $storeApplicationData->description }}</li>
          <li><strong>Address:</strong> {{ $storeApplicationData->address }}</li>
          <li><strong>Social Media:</strong></li>
                <ul>
                @foreach ($storeApplicationData->applicationSocials as $social)
                    <li>{{ $social->platform }}: {{ $social->user_name }}</li>
                @endforeach
                </ul>
        </ul>
      </div>

      <div class="email-footer">
        <div>© {{ date('Y') }} BlessedStores</div>
      </div>
    </div>
  </div>
</body>
</html>
