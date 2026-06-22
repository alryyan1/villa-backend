<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $query = Villa::with('owner');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }
        if ($request->has('is_managed') && $request->input('is_managed') !== '') {
            $query->where('is_managed', (bool) $request->input('is_managed'));
        }

        $perPage = min((int) $request->input('per_page', 20), 999);
        $paginated = $query->orderBy('name')->paginate($perPage);

        $today = now()->toDateString();
        $occupiedIds = Booking::whereIn('villa_id', $paginated->pluck('id'))
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->pluck('villa_id')
            ->flip()
            ->all();

        $paginated->getCollection()->transform(function ($villa) use ($occupiedIds) {
            if ($villa->status !== 'maintenance' && isset($occupiedIds[$villa->id])) {
                $villa->status = 'occupied';
            } elseif ($villa->status !== 'maintenance' && !isset($occupiedIds[$villa->id])) {
                $villa->status = 'available';
            }
            return $villa;
        });

        return response()->json($paginated);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:Seashell,Coral,Garden,Breeze,Pearl',
            'num_rooms' => 'nullable|integer|min:1|max:20',
            'status' => 'in:available,occupied,maintenance',
            'price_per_night' => 'required|numeric|min:0',
            'owner_id' => 'required|exists:owners,id',
            'is_managed' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $villa = Villa::create($validated);
        ActivityLogService::log('create_villa', 'Villa', $villa->id, ['name' => $villa->name]);

        return response()->json($villa->load('owner'), 201);
    }

    public function show(Villa $villa)
    {
        return response()->json($villa->load('owner'));
    }

    public function update(Request $request, Villa $villa)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:Seashell,Coral,Garden,Breeze,Pearl',
            'num_rooms' => 'nullable|integer|min:1|max:20',
            'status' => 'in:available,occupied,maintenance',
            'price_per_night' => 'sometimes|numeric|min:0',
            'owner_id' => 'sometimes|exists:owners,id',
            'is_managed' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $villa->update($validated);
        ActivityLogService::log('update_villa', 'Villa', $villa->id, ['name' => $villa->name]);

        return response()->json($villa->load('owner'));
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $total     = Villa::count();
        $managed   = Villa::where('is_managed', true)->count();
        $unmanaged = $total - $managed;

        return response()->json(compact('total', 'managed', 'unmanaged'));
    }

    public function destroy(Villa $villa)
    {
        ActivityLogService::log('delete_villa', 'Villa', $villa->id, ['name' => $villa->name]);
        $villa->delete();
        return response()->json(['message' => 'Villa deleted successfully.']);
    }

    public function bookings(Villa $villa, Request $request)
    {
        $query = $villa->bookings()->with(['guest', 'user']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->input('per_page', 20), 200);
        return response()->json($query->orderByDesc('check_in')->paginate($perPage));
    }

    public function calendar(Villa $villa, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$year, $m] = explode('-', $month);

        $bookings = $villa->bookings()
            ->with('guest')
            ->whereNotIn('status', ['cancelled'])
            ->whereYear('check_in', $year)
            ->whereMonth('check_in', $m)
            ->orWhere(function ($q) use ($year, $m) {
                $q->whereYear('check_out', $year)->whereMonth('check_out', $m);
            })
            ->get();

        return response()->json($bookings);
    }
}
