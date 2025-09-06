<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminLogsController extends Controller
{
    /**
     * Get system logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        ]);
    }

    /**
     * Get log statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_logs' => SystemLog::count(),
            'logs_today' => SystemLog::whereDate('created_at', today())->count(),
            'logs_this_week' => SystemLog::where('created_at', '>=', now()->subWeek())->count(),
            'logs_this_month' => SystemLog::where('created_at', '>=', now()->subMonth())->count(),
            'by_level' => SystemLog::selectRaw('level, COUNT(*) as count')
                ->groupBy('level')
                ->pluck('count', 'level'),
            'by_action' => SystemLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'action'),
            'recent_actions' => SystemLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get available filter options
     */
    public function filters(): JsonResponse
    {
        $filters = [
            'actions' => SystemLog::distinct()->pluck('action')->sort()->values(),
            'levels' => SystemLog::distinct()->pluck('level')->sort()->values(),
            'model_types' => SystemLog::distinct()->pluck('model_type')->filter()->sort()->values(),
            'users' => SystemLog::with('user')
                ->whereNotNull('user_id')
                ->get()
                ->pluck('user.name', 'user_id')
                ->unique()
        ];

        return response()->json(['data' => $filters]);
    }

    /**
     * Export logs to CSV
     */
    public function export(Request $request): JsonResponse
    {
        $query = SystemLog::with('user');

        // Apply same filters as index
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $csvData = $logs->map(function ($log) {
            return [
                'ID' => $log->id,
                'Date' => $log->created_at->format('Y-m-d H:i:s'),
                'User' => $log->user ? $log->user->name : 'System',
                'Action' => $log->action,
                'Description' => $log->description,
                'Level' => $log->level,
                'Model Type' => $log->model_type,
                'Model ID' => $log->model_id,
                'IP Address' => $log->ip_address,
                'User Agent' => $log->user_agent,
            ];
        });

        return response()->json([
            'data' => $csvData,
            'filename' => 'system_logs_' . now()->format('Y-m-d_H-i-s') . '.csv'
        ]);
    }
}