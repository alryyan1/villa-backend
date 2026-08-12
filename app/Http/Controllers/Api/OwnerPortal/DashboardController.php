<?php

namespace App\Http\Controllers\Api\OwnerPortal;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        $villasCount = count($villaIds);

        $upcomingBookings = Booking::with(['villa', 'guest'])
            ->whereIn('villa_id', $villaIds)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_out', '>=', now()->toDateString())
            ->orderBy('check_in')
            ->limit(10)
            ->get();

        $pendingCount = Booking::whereIn('villa_id', $villaIds)
            ->where('status', 'pending')
            ->count();

        $activeCount = Villa::whereIn('id', $villaIds)
            ->whereIn('status', ['available', 'occupied'])
            ->count();

        return response()->json([
            'villas_count'       => $villasCount,
            'active_villas'      => $activeCount,
            'pending_bookings'   => $pendingCount,
            'upcoming_bookings'  => $upcomingBookings,
        ]);
    }
}
