<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailConfigService
{
    /**
     * Check if email is properly configured
     */
    public function isEmailConfigured(): bool
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        
        return $mailer === 'smtp' && $host && $username && $password;
    }
    
    /**
     * Get email configuration status
     */
    public function getEmailStatus(): array
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        $port = config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption');
        
        return [
            'mailer' => $mailer,
            'host' => $host,
            'username' => $username,
            'password' => $password ? '***configured***' : 'not set',
            'port' => $port,
            'encryption' => $encryption,
            'configured' => $this->isEmailConfigured(),
        ];
    }
    
    /**
     * Test email configuration
     */
    public function testEmailConfiguration(string $testEmail): array
    {
        if (!$this->isEmailConfigured()) {
            return [
                'success' => false,
                'message' => 'Email not configured. Please check your .env file.',
                'config' => $this->getEmailStatus(),
            ];
        }
        
        try {
            // Try to send a test email
            Mail::raw('This is a test email from ProfitTrade OTP system.', function ($message) use ($testEmail) {
                $message->to($testEmail)
                        ->subject('ProfitTrade Email Test');
            });
            
            return [
                'success' => true,
                'message' => 'Test email sent successfully!',
                'config' => $this->getEmailStatus(),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
                'config' => $this->getEmailStatus(),
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get email configuration instructions
     */
    public function getConfigurationInstructions(): array
    {
        return [
            'gmail' => [
                'name' => 'Gmail SMTP',
                'instructions' => [
                    '1. Enable 2-factor authentication on your Gmail account',
                    '2. Generate an App Password (Google Account > Security > App Passwords)',
                    '3. Use your Gmail address as MAIL_USERNAME',
                    '4. Use the generated App Password as MAIL_PASSWORD',
                    '5. Set MAIL_HOST=smtp.gmail.com and MAIL_PORT=587',
                ],
                'env_example' => [
                    'MAIL_MAILER=smtp',
                    'MAIL_HOST=smtp.gmail.com',
                    'MAIL_PORT=587',
                    'MAIL_USERNAME=your-email@gmail.com',
                    'MAIL_PASSWORD=your-16-char-app-password',
                    'MAIL_ENCRYPTION=tls',
                    'MAIL_FROM_ADDRESS=your-email@gmail.com',
                    'MAIL_FROM_NAME="ProfitTrade"',
                ],
            ],
            'mailgun' => [
                'name' => 'Mailgun',
                'instructions' => [
                    '1. Sign up for Mailgun account',
                    '2. Add and verify your domain',
                    '3. Get your API key from Mailgun dashboard',
                    '4. Use your domain as MAILGUN_DOMAIN',
                    '5. Use your API key as MAILGUN_SECRET',
                ],
                'env_example' => [
                    'MAIL_MAILER=mailgun',
                    'MAILGUN_DOMAIN=your-domain.mailgun.org',
                    'MAILGUN_SECRET=your-mailgun-api-key',
                    'MAIL_FROM_ADDRESS=noreply@yourdomain.com',
                    'MAIL_FROM_NAME="ProfitTrade"',
                ],
            ],
            'sendgrid' => [
                'name' => 'SendGrid',
                'instructions' => [
                    '1. Sign up for SendGrid account',
                    '2. Verify your sender email address',
                    '3. Generate an API key',
                    '4. Use "apikey" as MAIL_USERNAME',
                    '5. Use your API key as MAIL_PASSWORD',
                ],
                'env_example' => [
                    'MAIL_MAILER=smtp',
                    'MAIL_HOST=smtp.sendgrid.net',
                    'MAIL_PORT=587',
                    'MAIL_USERNAME=apikey',
                    'MAIL_PASSWORD=your-sendgrid-api-key',
                    'MAIL_ENCRYPTION=tls',
                    'MAIL_FROM_ADDRESS=noreply@yourdomain.com',
                    'MAIL_FROM_NAME="ProfitTrade"',
                ],
            ],
        ];
    }
}
