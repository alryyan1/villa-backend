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
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhereHas('owner', fn($o) => $o->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }
        if ($request->boolean('contract_active')) {
            $today = now()->toDateString();
            $query->whereDate('contract_start_date', '<=', $today)
                  ->whereDate('contract_end_date', '>=', $today);
        }

        $perPage = min((int) $request->input('per_page', 20), 999);
        $paginated = $query->orderBy('name')->paginate($perPage);

        $today = now()->toDateString();
        $villaIds = $paginated->pluck('id');

        $occupiedIds = Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->pluck('villa_id')
            ->flip()
            ->all();

        $checkingInTodayIds = Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereNotNull('checked_in_at')
            ->whereDate('check_out', '>=', $today)
            ->pluck('villa_id')
            ->flip()
            ->all();

        $paginated->getCollection()->transform(function ($villa) use ($occupiedIds, $checkingInTodayIds) {
            if ($villa->status !== 'maintenance' && isset($occupiedIds[$villa->id])) {
                $villa->status = 'occupied';
            } elseif ($villa->status !== 'maintenance' && !isset($occupiedIds[$villa->id])) {
                $villa->status = 'available';
            }
            $villa->checking_in_today = isset($checkingInTodayIds[$villa->id]);
            return $villa;
        });

        return response()->json($paginated);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string',
            'category'               => 'nullable|in:Seashell,Coral,Garden,Breeze,Pearl',
            'num_rooms'              => 'nullable|integer|min:1|max:20',
            'status'                 => 'in:available,occupied,maintenance',
            'price_per_night'        => 'required|numeric|min:0',
            'owner_id'               => 'required|exists:owners,id',
            'notes'                  => 'nullable|string',
            'contract_start_date'    => 'nullable|date',
            'contract_end_date'      => 'nullable|date|after_or_equal:contract_start_date',
            'contract_monthly_value' => 'nullable|numeric|min:0',
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
            'name'                   => 'sometimes|string|max:255',
            'description'            => 'nullable|string',
            'category'               => 'nullable|in:Seashell,Coral,Garden,Breeze,Pearl',
            'num_rooms'              => 'nullable|integer|min:1|max:20',
            'status'                 => 'in:available,occupied,maintenance',
            'price_per_night'        => 'sometimes|numeric|min:0',
            'owner_id'               => 'sometimes|exists:owners,id',
            'notes'                  => 'nullable|string',
            'contract_start_date'    => 'nullable|date',
            'contract_end_date'      => 'nullable|date|after_or_equal:contract_start_date',
            'contract_monthly_value' => 'nullable|numeric|min:0',
        ]);

        $villa->update($validated);
        ActivityLogService::log('update_villa', 'Villa', $villa->id, ['name' => $villa->name]);

        return response()->json($villa->load('owner'));
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $total    = Villa::count();
        $today    = now()->toDateString();
        $active   = Villa::whereDate('contract_start_date', '<=', $today)
                         ->whereDate('contract_end_date', '>=', $today)
                         ->count();

        return response()->json(['total' => $total, 'active_contracts' => $active]);
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
