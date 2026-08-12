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
        if ($request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Villa::with('owner');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhereHas('owner', fn($o) => $o->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->category) {
            $query->where('category', $request->category);
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

        $today      = now()->toDateString();
        $asOfStart  = $request->input('as_of_start', $request->input('as_of', $today));
        $asOfEnd    = $request->input('as_of_end', $request->input('as_of', $today));
        $isToday    = $asOfStart === $today && $asOfEnd === $today;
        $isSingleDay = $asOfStart === $asOfEnd;
        $villaIds   = $paginated->pluck('id');

        // A villa is "occupied" if any booking overlaps the viewed window.
        // Single-day view: asOfStart/asOfEnd is one calendar day — a booking occupies it if it
        // covers that night, i.e. check_in <= day < check_out (checkout day itself is free again).
        // Multi-day range view: the range is treated as a candidate stay (check-in = asOfStart,
        // check-out = asOfEnd), so it uses the same half-open overlap test as new-booking conflict
        // checks (check_in < asOfEnd AND check_out > asOfStart) — a booking that checks out exactly
        // on asOfStart or checks in exactly on asOfEnd does not block it (same-day turnover).
        $occupiedIds = Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', $isSingleDay ? '<=' : '<', $asOfEnd)
            ->whereDate('check_out', '>', $asOfStart)
            ->pluck('villa_id')
            ->flip()
            ->all();

        // Guests physically inside (arrived today or earlier, not yet departed) — only meaningful for the "today" view
        $checkingInTodayIds = $isToday
            ? Booking::whereIn('villa_id', $villaIds)
                ->whereIn('status', ['confirmed', 'pending'])
                ->whereNotNull('checked_in_at')
                ->whereDate('check_out', '>=', $today)
                ->pluck('villa_id')
                ->flip()
                ->all()
            : [];

        // Check-in date is today but guest has NOT arrived yet
        $awaitingArrivalIds = $isToday
            ? Booking::whereIn('villa_id', $villaIds)
                ->whereIn('status', ['confirmed', 'pending'])
                ->whereDate('check_in', $today)
                ->whereNull('checked_in_at')
                ->pluck('villa_id')
                ->flip()
                ->all()
            : [];

        // Check-out date is today but departure hasn't been confirmed yet
        $checkingOutTodayIds = $isToday
            ? Booking::whereIn('villa_id', $villaIds)
                ->whereIn('status', ['confirmed', 'pending'])
                ->whereDate('check_out', $today)
                ->whereNull('checked_out_at')
                ->pluck('villa_id')
                ->flip()
                ->all()
            : [];

        $activeBookings = Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', $isSingleDay ? '<=' : '<', $asOfEnd)
            ->whereDate('check_out', '>', $asOfStart)
            ->with('guest:id,name')
            ->get(['id', 'villa_id', 'guest_id', 'check_in', 'check_out', 'checked_in_at', 'checked_out_at', 'payment_status'])
            ->keyBy('villa_id');

        // Bookings count per villa for the current calendar month (by check-in date)
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();
        $monthlyBookingCounts = Booking::whereIn('villa_id', $villaIds)
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('check_in', '>=', $monthStart)
            ->whereDate('check_in', '<=', $monthEnd)
            ->selectRaw('villa_id, count(*) as cnt')
            ->groupBy('villa_id')
            ->pluck('cnt', 'villa_id');

        $paginated->getCollection()->transform(function ($villa) use ($occupiedIds, $checkingInTodayIds, $awaitingArrivalIds, $checkingOutTodayIds, $activeBookings, $monthlyBookingCounts) {
            $statusLocked = in_array($villa->status, ['maintenance', 'blocked'], true);
            if (!$statusLocked && isset($occupiedIds[$villa->id])) {
                $villa->status = 'occupied';
            } elseif (!$statusLocked && !isset($occupiedIds[$villa->id])) {
                $villa->status = 'available';
            }
            $villa->checking_in_today   = isset($checkingInTodayIds[$villa->id]);
            $villa->awaiting_arrival    = isset($awaitingArrivalIds[$villa->id]);
            $villa->checking_out_today  = isset($checkingOutTodayIds[$villa->id]);
            $booking = $activeBookings->get($villa->id);
            $villa->active_booking_id          = $booking?->id;
            $villa->active_booking_guest        = $booking?->guest?->name;
            $villa->active_booking_checked_in   = (bool) $booking?->checked_in_at;
            $villa->active_booking_checked_out  = (bool) $booking?->checked_out_at;
            $villa->active_booking_payment      = $booking?->payment_status;
            $villa->active_booking_check_in     = $booking?->check_in?->toDateString();
            $villa->active_booking_check_out    = $booking?->check_out?->toDateString();
            $villa->monthly_bookings_count       = (int) ($monthlyBookingCounts->get($villa->id) ?? 0);
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
            'status'                 => 'in:available,occupied,maintenance,blocked',
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

    public function show(Request $request, Villa $villa)
    {
        if ($request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($villa->load('owner'));
    }

    public function update(Request $request, Villa $villa)
    {
        $validated = $request->validate([
            'name'                   => 'sometimes|string|max:255',
            'description'            => 'nullable|string',
            'category'               => 'nullable|in:Seashell,Coral,Garden,Breeze,Pearl',
            'num_rooms'              => 'nullable|integer|min:1|max:20',
            'status'                 => 'in:available,occupied,maintenance,blocked',
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
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Overlap test (not "check_in/check_out falls inside the month") so a stay
        // that spans the entire month without either edge landing in it still shows.
        $bookings = $villa->bookings()
            ->with('guest')
            ->whereNotIn('status', ['cancelled'])
            ->where('check_in', '<=', $end->format('Y-m-d'))
            ->where('check_out', '>=', $start->format('Y-m-d'))
            ->get();

        return response()->json($bookings);
    }
}
