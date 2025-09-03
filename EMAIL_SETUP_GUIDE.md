# 📧 Email Setup Guide for ProfitTrade OTP System

## 🚀 Quick Start

### 1. Check Current Email Configuration
```bash
php artisan email:check
```

### 2. Test Email Configuration
```bash
php artisan email:test your-email@gmail.com
```

## 🔧 Email Provider Setup

### Option 1: Gmail SMTP (Recommended for Development)

#### Step 1: Enable 2-Factor Authentication
1. Go to [Google Account Settings](https://myaccount.google.com/)
2. Navigate to Security → 2-Step Verification
3. Enable 2-Step Verification

#### Step 2: Generate App Password
1. Go to Security → App Passwords
2. Select "Mail" and "Other (Custom name)"
3. Enter "ProfitTrade" as the name
4. Copy the generated 16-character password

#### Step 3: Update .env File
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-char-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="ProfitTrade"
```

### Option 2: Mailgun (Production Ready)

#### Step 1: Sign Up
1. Go to [Mailgun](https://www.mailgun.com/)
2. Create an account and verify your domain

#### Step 2: Get API Key
1. Go to Settings → API Keys
2. Copy your Private API Key

#### Step 3: Update .env File
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-api-key
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="ProfitTrade"
```

### Option 3: SendGrid (Production Ready)

#### Step 1: Sign Up
1. Go to [SendGrid](https://sendgrid.com/)
2. Create an account and verify your sender email

#### Step 2: Generate API Key
1. Go to Settings → API Keys
2. Create a new API Key with "Mail Send" permissions

#### Step 3: Update .env File
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="ProfitTrade"
```

## 🧪 Testing the Email System

### 1. Check Configuration
```bash
php artisan email:check
```

### 2. Test Email Sending
```bash
php artisan email:test your-email@example.com
```

### 3. Test OTP Flow
1. Start the Laravel server: `php artisan serve`
2. Go to `/login` in your browser
3. Enter demo credentials: `demo@profittrade.com` / `password`
4. Check your email for OTP
5. Enter OTP in the verification screen

## 🔍 Troubleshooting

### Common Issues

#### Issue: "Failed to send email"
**Solution:**
1. Check your .env file configuration
2. Verify SMTP credentials
3. Check firewall/port settings
4. For Gmail: Ensure App Password is used, not regular password

#### Issue: "Connection refused"
**Solution:**
1. Check MAIL_HOST and MAIL_PORT
2. Verify firewall settings
3. Try different ports (587, 465, 25)

#### Issue: "Authentication failed"
**Solution:**
1. Double-check username and password
2. For Gmail: Use App Password, not regular password
3. Ensure 2FA is enabled for Gmail

### Debug Commands

```bash
# Check email configuration
php artisan email:check

# Test email sending
php artisan email:test your-email@example.com

# Check Laravel logs
tail -f storage/logs/laravel.log

# Clear config cache
php artisan config:clear
```

## 📱 Frontend Integration

The OTP system is fully integrated with the frontend:

- **Login Flow**: Password → OTP → Dashboard
- **Registration Flow**: Form → OTP → Dashboard
- **Resend OTP**: Rate-limited with 60-second cooldown
- **Error Handling**: Clear feedback for all scenarios

## 🚀 Production Deployment

### 1. Update Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
MAIL_MAILER=mailgun  # or sendgrid, ses
```

### 2. Use Production Email Service
- **Mailgun**: Best for transactional emails
- **SendGrid**: Great deliverability
- **AWS SES**: Cost-effective for high volume

### 3. Monitor Email Delivery
- Check email logs
- Monitor bounce rates
- Set up email analytics

## 📊 Email Templates

The OTP email template is located at:
```
resources/views/emails/otp.blade.php
```

### Customization
- Update colors and branding
- Modify content and messaging
- Add company logo
- Include tracking pixels

## 🔒 Security Features

- **OTP Expiry**: 5-minute validity
- **Rate Limiting**: 60-second resend cooldown
- **Account Lockout**: 5 failed attempts = 15-minute lockout
- **Secure Storage**: OTPs not stored in plain text
- **Automatic Cleanup**: OTPs cleared after verification

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Run `php artisan email:check`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Test with `php artisan email:test`

---

**🎯 The OTP system is now fully functional with real email delivery!**
