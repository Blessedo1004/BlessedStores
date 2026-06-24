<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .success-container {
            background: #f0fff0;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #4CAF50;
        }
        h1 {
            color: #4CAF50;
        }
        .details {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .btn {
            display: inline-block;
            background: #0A4B78;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <h1>Payment Successful!</h1>
        <p>Thank you for your payment. Here are your transaction details:</p>
        
        <div class="details">
            <div class="detail-item">
                <span class="detail-label">Name:</span>
                <span>{{ $name }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span>{{ $email }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <span>{{ $phone }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Amount:</span>
                <span>₦{{ number_format($amount, 2) }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Reference:</span>
                <span>{{ $reference }}</span>
            </div>
        </div>
        
        <a href="{{ route('payment.form') }}" class="btn">Make Another Payment</a>
    </div>
</body>
</html>