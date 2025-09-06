<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user to send notifications to
        $user = User::first();
        
        if (!$user) {
            $this->command->info('No users found. Please create a user first.');
            return;
        }

        // Create some sample notifications
        $notifications = [
            [
                'user_id' => $user->id,
                'title' => 'Welcome to ProfitTrade!',
                'body' => 'Welcome to our trading platform. Start your trading journey with our advanced tools and features.',
                'type' => 'success',
                'meta' => ['category' => 'welcome']
            ],
            [
                'user_id' => $user->id,
                'title' => 'System Maintenance',
                'body' => 'Scheduled maintenance will occur tonight from 2:00 AM to 4:00 AM UTC. Trading will be temporarily unavailable.',
                'type' => 'warning',
                'meta' => ['category' => 'maintenance', 'scheduled_time' => '2024-01-15 02:00:00']
            ],
            [
                'user_id' => $user->id,
                'title' => 'New Trading Pair Added',
                'body' => 'We have added BTC/USDT to our available trading pairs. Start trading now!',
                'type' => 'info',
                'meta' => ['category' => 'trading', 'pair' => 'BTC/USDT']
            ],
            [
                'user_id' => $user->id,
                'title' => 'Deposit Successful',
                'body' => 'Your deposit of $100.00 has been successfully processed and added to your account.',
                'type' => 'success',
                'meta' => ['category' => 'financial', 'amount' => 100.00]
            ],
            [
                'user_id' => $user->id,
                'title' => 'Security Alert',
                'body' => 'We detected a login from a new device. If this was not you, please secure your account immediately.',
                'type' => 'error',
                'meta' => ['category' => 'security', 'action_required' => true]
            ]
        ];

        foreach ($notifications as $notificationData) {
            Notification::create($notificationData);
        }

        $this->command->info('Created ' . count($notifications) . ' sample notifications for user: ' . $user->name);
    }
}