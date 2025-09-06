<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'slug',
        'details',
        'is_active',
        'min_amount',
        'max_amount',
        'fee_percentage',
        'fee_fixed',
        'instructions',
        'required_fields',
        'processing_time_minutes',
    ];

    protected $casts = [
        'details' => 'array',
        'required_fields' => 'array',
        'is_active' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'fee_percentage' => 'decimal:2',
        'fee_fixed' => 'decimal:2',
    ];

    /**
     * Get deposits using this payment method
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * Scope for active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get formatted details for display
     */
    public function getFormattedDetailsAttribute()
    {
        $details = $this->details;
        $formatted = [];

        foreach ($details as $key => $value) {
            $formatted[ucfirst(str_replace('_', ' ', $key))] = $value;
        }

        return $formatted;
    }

    /**
     * Calculate total fee for an amount
     */
    public function calculateFee($amount)
    {
        $percentageFee = ($amount * $this->fee_percentage) / 100;
        return $percentageFee + $this->fee_fixed;
    }

    /**
     * Check if amount is within limits
     */
    public function isAmountValid($amount)
    {
        return $amount >= $this->min_amount && $amount <= $this->max_amount;
    }
}