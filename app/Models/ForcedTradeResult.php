<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForcedTradeResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_id',
        'forced_result',
        'reason',
        'admin_id',
        'is_applied',
        'applied_at',
    ];

    protected $casts = [
        'is_applied' => 'boolean',
        'applied_at' => 'datetime',
    ];

    /**
     * Get the trade that this forced result belongs to.
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Get the admin who forced this result.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Mark this forced result as applied.
     */
    public function markAsApplied(): void
    {
        $this->update([
            'is_applied' => true,
            'applied_at' => now(),
        ]);
    }

    /**
     * Check if a trade has a forced result.
     */
    public static function hasForcedResult(int $tradeId): bool
    {
        return static::where('trade_id', $tradeId)->exists();
    }

    /**
     * Get the forced result for a trade.
     */
    public static function getForcedResult(int $tradeId): ?string
    {
        $forcedResult = static::where('trade_id', $tradeId)->first();
        return $forcedResult ? $forcedResult->forced_result : null;
    }

    /**
     * Create a forced result for a trade.
     */
    public static function createForcedResult(int $tradeId, string $result, int $adminId, ?string $reason = null): self
    {
        return static::create([
            'trade_id' => $tradeId,
            'forced_result' => $result,
            'admin_id' => $adminId,
            'reason' => $reason,
        ]);
    }
}
