<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AdminNotificationController extends Controller
{
    /**
     * Get all notifications with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('user');

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    /**
     * Get a specific notification
     */
    public function show($id): JsonResponse
    {
        $notification = Notification::with('user')->findOrFail($id);
        
        return response()->json([
            'data' => $notification
        ]);
    }

    /**
     * Create a new notification
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'is_global' => 'boolean',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'required|string',
            'priority' => 'in:low,medium,high,urgent',
            'expires_at' => 'nullable|date|after:now',
            'meta' => 'nullable|array',
        ]);

        // If global notification, user_id should be null
        if ($request->is_global) {
            $request->merge(['user_id' => null]);
        }

        $notification = Notification::create([
            'user_id' => $request->user_id,
            'is_global' => $request->is_global ?? false,
            'created_by' => auth()->id(),
            'title' => $request->title,
            'body' => $request->body,
            'type' => $request->type,
            'priority' => $request->priority ?? 'medium',
            'expires_at' => $request->expires_at,
            'meta' => $request->meta,
        ]);

        // Log the notification creation
        LoggingService::logNotificationCreated($notification, request());

        return response()->json([
            'message' => $request->is_global ? 'Global notification sent successfully' : 'Notification created successfully',
            'data' => $notification->load(['user', 'creator'])
        ], 201);
    }

    /**
     * Update a notification
     */
    public function update(Request $request, $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'type' => 'sometimes|required|string',
            'meta' => 'nullable|array',
        ]);

        $notification->update($request->only([
            'user_id', 'title', 'body', 'type', 'meta'
        ]));

        return response()->json([
            'message' => 'Notification updated successfully',
            'data' => $notification->load('user')
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy($id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Notification::count(),
            'unread' => Notification::unread()->count(),
            'read' => Notification::read()->count(),
            'by_type' => Notification::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get users for target audience selection
     */
    public function getUsers(Request $request): JsonResponse
    {
        $query = User::select('id', 'name', 'email', 'is_admin');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(50)->get();

        return response()->json(['data' => $users]);
    }
}
