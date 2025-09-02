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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'demo_balance' => 'decimal:2',
        'live_balance' => 'decimal:2',
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
}
