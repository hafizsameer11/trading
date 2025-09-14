<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use App\Models\User;

class TestTradeNotification extends Command
{
    protected $signature = 'test:trade-notification {user_id} {result=WIN}';
    protected $description = 'Test trade settlement notification for a user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $result = $this->argument('result');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $isWin = $result === 'WIN';
        $title = $isWin ? '🎉 Trade Won!' : '❌ Trade Lost';
        $body = $isWin 
            ? "Your UP trade on XAU/USD won! You earned $189.00"
            : "Your DOWN trade on XAU/USD lost. Amount: $100.00";

        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $isWin ? 'success' : 'error',
            'priority' => 'high',
            'meta' => json_encode([
                'trade_id' => 999,
                'result' => $result,
                'amount' => 100.00,
                'payout' => $isWin ? 189.00 : 0,
                'pair_symbol' => 'XAU/USD',
                'direction' => $isWin ? 'UP' : 'DOWN',
            ]),
        ]);

        $this->info("Created test notification for user {$user->name} ({$user->email})");
        $this->info("Title: {$title}");
        $this->info("Body: {$body}");
        
        return 0;
    }
}

