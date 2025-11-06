<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful - District Smiles</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { color: green; font-size: 24px; }
        .info { color: #666; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="success">✅ Payment Successful!</div>
    <div class="info">
        <p>Your payment has been received and your appointment is being confirmed.</p>
        <p>You will receive an email confirmation shortly.</p>
    </div>
    <a href="{{ route('customer.appointment') }}">Return to Appointments</a>
</body>
</html>