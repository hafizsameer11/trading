<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pair extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'slug',
        'type',
        'is_active',
        'base_currency',
        'quote_currency',
        'trend_mode',
        'volatility',
        'min_price',
        'max_price',
        'price_precision',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_price' => 'decimal:8',
        'max_price' => 'decimal:8',
        'price_precision' => 'integer',
        'meta' => 'array',
    ];

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLive($query)
    {
        return $query->where('type', 'LIVE');
    }

    public function scopeOtc($query)
    {
        return $query->where('type', 'OTC');
    }
}
