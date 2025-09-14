<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Server-Sent Events stream for real-time notifications
     */
    public function stream(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Cache-Control');

        $userId = Auth::id();
        $lastNotificationId = 0;

        // Send initial connection message
        $response->setContent("data: " . json_encode(['type' => 'connected', 'user_id' => $userId]) . "\n\n");
        
        // Keep connection alive and check for new notifications
        $response->setCallback(function () use ($userId, &$lastNotificationId) {
            $notifications = Notification::where('user_id', $userId)
                ->where('id', '>', $lastNotificationId)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($notifications as $notification) {
                $data = [
                    'type' => 'notification',
                    'notification' => $notification->toArray(),
                ];
                
                echo "data: " . json_encode($data) . "\n\n";
                $lastNotificationId = $notification->id;
            }

            // Send heartbeat every 30 seconds
            echo "data: " . json_encode(['type' => 'heartbeat', 'timestamp' => time()]) . "\n\n";
            
            if (connection_aborted()) {
                return false;
            }
            
            sleep(2); // Check every 2 seconds
            return true;
        });

        return $response;
    }
}