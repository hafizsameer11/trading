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
    ];

    protected $casts = [
        'daily_win_percent' => 'decimal:2',
        'otc_tick_ms' => 'integer',
    ];

    public static function instance()
    {
        return static::firstOrCreate(['id' => 1], [
            'daily_win_percent' => 50.00,
            'otc_tick_ms' => 1000,
        ]);
    }
}
