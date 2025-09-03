<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate a new OTP for user
     */
    public function generateOtp(User $user): string
    {
        // Generate 6-digit OTP
        $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiry time (5 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(5);
        
        // Update user with OTP
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => $expiresAt,
        ]);
        
        return $otp;
    }
    
    /**
     * Send OTP email to user
     */
    public function sendOtpEmail(User $user, string $otp): bool
    {
        try {
            // For now, we'll log the OTP (in production, use real email service)
            Log::info("OTP for {$user->email}: {$otp}");
            
            // TODO: Replace with actual email service (Mailgun, SendGrid, etc.)
            // Mail::to($user->email)->send(new OtpMail($user, $otp));
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify OTP for user
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        if (!$user->isOtpValid()) {
            return false;
        }
        
        if ($user->otp !== $otp) {
            return false;
        }
        
        // Clear OTP after successful verification
        $user->clearOtp();
        
        return true;
    }
    
    /**
     * Check if user needs OTP verification
     */
    public function needsOtpVerification(User $user): bool
    {
        // Always require OTP for login (email factor authentication)
        return true;
    }
    
    /**
     * Resend OTP for user
     */
    public function resendOtp(User $user): array
    {
        // Check if user is locked
        if ($user->isLocked()) {
            return [
                'success' => false,
                'message' => 'Account is temporarily locked due to too many failed attempts. Please try again in 15 minutes.',
            ];
        }
        
        // Generate new OTP
        $otp = $this->generateOtp($user);
        
        // Send OTP email
        $emailSent = $this->sendOtpEmail($user, $otp);
        
        if ($emailSent) {
            return [
                'success' => true,
                'message' => 'OTP has been sent to your email address.',
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
        ];
    }
}
