<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketData extends Model
{
    use HasFactory;

    protected $fillable = [
        'pair_id',
        'pair_symbol',
        'timeframe_sec',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected $casts = [
        'timestamp' => 'integer',
        'timeframe_sec' => 'integer',
        'open' => 'decimal:8',
        'high' => 'decimal:8',
        'low' => 'decimal:8',
        'close' => 'decimal:8',
        'volume' => 'decimal:8',
    ];

    public function pair(): BelongsTo
    {
        return $this->belongsTo(Pair::class);
    }
}
