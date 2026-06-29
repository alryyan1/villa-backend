<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CleaningLog;
use Illuminate\Http\Request;

class CleaningLogController extends Controller
{
    /**
     * Most recent cleaning log per villa from the last 7 days,
     * keyed by villa_id — covers multi-day turnover gaps.
     */
    public function recent()
    {
        $logs = CleaningLog::with('user')
            ->where('cleaned_at', '>=', now()->subDays(7)->startOfDay())
            ->orderByDesc('cleaned_at')
            ->get()
            ->groupBy('villa_id')
            ->map(fn($group) => $group->first())
            ->map(fn($log) => [
                'id'         => $log->id,
                'cleaned_at' => $log->cleaned_at->toIso8601String(),
                'notes'      => $log->notes,
                'user_name'  => $log->user->name,
            ]);

        return response()->json($logs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'villa_id'   => 'required|exists:villas,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $log = CleaningLog::create([
            ...$data,
            'user_id'    => auth()->id(),
            'cleaned_at' => now(),
        ]);

        $log->load('user');

        return response()->json([
            'id'         => $log->id,
            'cleaned_at' => $log->cleaned_at->toIso8601String(),
            'notes'      => $log->notes,
            'user_name'  => $log->user->name,
        ], 201);
    }

    public function destroy(CleaningLog $cleaningLog)
    {
        $cleaningLog->delete();
        return response()->json(null, 204);
    }
}
