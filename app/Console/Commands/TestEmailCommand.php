<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    protected $signature = 'email:test {email}';
    protected $description = 'Test email sending functionality';

    public function handle()
    {
        $email = $this->argument('email');
        
        // Find or create a test user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found!");
            return 1;
        }
        
        $this->info("Testing email sending to: {$email}");
        
        try {
            // Generate a test OTP
            $otp = '123456';
            
            // Send test email
            Mail::to($user->email)->send(new OtpMail($user, $otp, 5));
            
            $this->info("✅ Test email sent successfully!");
            $this->info("Check your email inbox for the OTP verification email.");
            $this->info("Test OTP: {$otp}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            $this->error("Check your email configuration in .env file");
            return 1;
        }
        
        return 0;
    }
}
