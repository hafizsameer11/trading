<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pair_id',
        'pair_symbol',
        'timeframe_sec',
        'direction',
        'amount',
        'entry_price',
        'closing_price',
        'expiry_at',
        'result',
        'settled_at',
        'payout_rate',
        'account_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_price' => 'decimal:8',
        'closing_price' => 'decimal:8',
        'expiry_at' => 'datetime',
        'settled_at' => 'datetime',
        'payout_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pair()
    {
        return $this->belongsTo(Pair::class);
    }

    public function forcedResult()
    {
        return $this->hasOne(ForcedTradeResult::class);
    }

    public function scopePending($query)
    {
        return $query->where('result', 'PENDING');
    }

    public function scopeSettled($query)
    {
        return $query->where('result', '!=', 'PENDING');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function getPayoutAttribute()
    {
        if ($this->result === 'WIN') {
            return $this->amount + ($this->amount * $this->payout_rate / 100);
        }
        return 0;
    }

    public function getPnlAttribute()
    {
        if ($this->result === 'WIN') {
            return $this->payout - $this->amount;
        } elseif ($this->result === 'LOSE') {
            return -$this->amount;
        }
        return 0;
    }
}
