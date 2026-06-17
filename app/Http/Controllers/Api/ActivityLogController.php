<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->action) {
            $query->where('action', 'like', "%{$request->action}%");
        }
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json($query->paginate(50));
    }
}
