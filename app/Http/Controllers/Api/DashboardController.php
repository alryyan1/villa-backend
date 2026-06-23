<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $today     = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $totalVillas     = Villa::count();
        $activeContracts = Villa::whereDate('contract_start_date', '<=', $today->toDateString())
                                ->whereDate('contract_end_date', '>=', $today->toDateString())
                                ->count();
        $availableVillas = Villa::where('status', 'available')->count();
        $occupiedVillas  = Villa::where('status', 'occupied')->count();

        $currentBookings = Booking::whereNotIn('status', ['cancelled'])
            ->where('check_in', '<=', $today)
            ->where('check_out', '>=', $today)
            ->count();

        $upcomingBookings = Booking::whereNotIn('status', ['cancelled'])
            ->where('check_in', '>', $today)
            ->where('check_in', '<=', $today->copy()->addDays(7))
            ->count();

        $monthlyRevenue = Booking::where('status', 'confirmed')
            ->where('check_in', '>=', $thisMonth)
            ->sum('total_amount');

        $monthlyBookings = Booking::whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $occupancyRate = $totalVillas > 0
            ? round(($currentBookings / $totalVillas) * 100, 1)
            : 0;

        $recentBookings = Booking::with(['villa', 'guest', 'user'])
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $checkingInToday = Booking::with(['villa', 'guest'])
            ->where('check_in', $today)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $checkingOutToday = Booking::with(['villa', 'guest'])
            ->where('check_out', $today)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        // Revenue last 6 months
        $revenueChart = Booking::select(
                DB::raw('DATE_FORMAT(check_in, "%Y-%m") as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->where('status', 'confirmed')
            ->where('check_in', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'total_villas'       => $totalVillas,
            'active_contracts'   => $activeContracts,
            'available_villas'   => $availableVillas,
            'occupied_villas'    => $occupiedVillas,
            'current_bookings'   => $currentBookings,
            'upcoming_bookings'  => $upcomingBookings,
            'monthly_revenue'    => $monthlyRevenue,
            'monthly_bookings'   => $monthlyBookings,
            'occupancy_rate'     => $occupancyRate,
            'recent_bookings'    => $recentBookings,
            'checking_in_today'  => $checkingInToday,
            'checking_out_today' => $checkingOutToday,
            'revenue_chart'      => $revenueChart,
        ]);
    }
}
