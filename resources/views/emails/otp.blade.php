<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProfitTrade Verification Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
            text-align: center;
        }
        .otp-container {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.3);
        }
        .otp-code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 8px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
        }
        .expiry-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            color: #6c757d;
        }
        .expiry-info strong {
            color: #495057;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .security-note {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            margin: 20px 0;
            transition: transform 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 ProfitTrade</h1>
            <p>Your Verification Code</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $user->name }}!</h2>
            <p>You've requested a verification code to access your ProfitTrade account.</p>
            
            <div class="otp-container">
                <h3>Your Verification Code</h3>
                <div class="otp-code">{{ $otp }}</div>
                <p>Enter this code in the verification screen to complete your login.</p>
            </div>
            
            <div class="expiry-info">
                <strong>⏰ Code Expires In:</strong> {{ $expiryMinutes }} minutes<br>
                <strong>📧 Sent To:</strong> {{ $user->email }}
            </div>
            
            <div class="security-note">
                <strong>🔒 Security Notice:</strong> This code is for your use only. 
                Never share it with anyone, including ProfitTrade support staff. 
                If you didn't request this code, please ignore this email.
            </div>
            
            <p>If you have any questions, please contact our support team.</p>
            
            <a href="{{ config('app.url') }}" class="btn">Visit ProfitTrade</a>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} ProfitTrade. All rights reserved.</p>
            <p>This email was sent to {{ $user->email }} because you requested account verification.</p>
            <p>
                <a href="{{ config('app.url') }}/unsubscribe">Unsubscribe</a> | 
                <a href="{{ config('app.url') }}/privacy">Privacy Policy</a> | 
                <a href="{{ config('app.url') }}/terms">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
