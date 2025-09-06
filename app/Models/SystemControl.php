<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_win_percent',
        'otc_tick_ms',
        'morning_trend',
        'afternoon_trend',
        'evening_trend',
        'morning_start',
        'morning_end',
        'afternoon_start',
        'afternoon_end',
        'evening_start',
        'evening_end',
        'trend_strength',
    ];

    protected $casts = [
        'daily_win_percent' => 'decimal:2',
        'otc_tick_ms' => 'integer',
        'trend_strength' => 'decimal:1',
    ];

    public static function instance()
    {
        return static::firstOrCreate(['id' => 1], [
            'daily_win_percent' => 50.00,
            'otc_tick_ms' => 1000,
            'morning_trend' => 'SIDEWAYS',
            'afternoon_trend' => 'SIDEWAYS',
            'evening_trend' => 'SIDEWAYS',
            'morning_start' => '09:00:00',
            'morning_end' => '12:00:00',
            'afternoon_start' => '12:00:00',
            'afternoon_end' => '17:00:00',
            'evening_start' => '17:00:00',
            'evening_end' => '21:00:00',
            'trend_strength' => 5.0,
        ]);
    }
}
