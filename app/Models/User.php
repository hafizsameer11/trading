<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'referral_code',
        'is_admin',
        'demo_balance',
        'live_balance',
        'email_verified_at',
        'otp',
        'otp_expires_at',
        'login_attempts',
        'last_login_attempt',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'demo_balance' => 'decimal:2',
        'live_balance' => 'decimal:2',
        'otp_expires_at' => 'datetime',
        'last_login_attempt' => 'datetime',
    ];

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function getBalanceAttribute($accountType)
    {
        return $accountType === 'live' ? $this->live_balance : $this->demo_balance;
    }

    public function updateBalance($accountType, $amount)
    {
        $field = $accountType === 'live' ? 'live_balance' : 'demo_balance';
        $this->increment($field, $amount);
    }

    public function isOtpValid()
    {
        return $this->otp && $this->otp_expires_at && $this->otp_expires_at->isFuture();
    }

    public function clearOtp()
    {
        $this->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);
    }

    public function incrementLoginAttempts()
    {
        $this->increment('login_attempts');
        $this->update(['last_login_attempt' => now()]);
    }

    public function resetLoginAttempts()
    {
        $this->update([
            'login_attempts' => 0,
            'last_login_attempt' => null,
        ]);
    }

    public function isLocked()
    {
        return $this->login_attempts >= 5 && 
               $this->last_login_attempt && 
               $this->last_login_attempt->addMinutes(15)->isFuture();
    }
}
