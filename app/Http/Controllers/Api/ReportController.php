<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function occupancy(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $villas = Villa::with(['bookings' => function ($q) use ($from, $to) {
            $q->whereNotIn('status', ['cancelled'])
              ->where(function ($q2) use ($from, $to) {
                  $q2->whereBetween('check_in', [$from, $to])
                     ->orWhereBetween('check_out', [$from, $to])
                     ->orWhere(fn($q3) => $q3->where('check_in', '<=', $from)->where('check_out', '>=', $to));
              });
        }])->get()->map(function ($villa) use ($from, $to) {
            $totalDays    = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $bookedNights = $villa->bookings->sum('nights');
            return [
                'id'             => $villa->id,
                'name'           => $villa->name,
                'total_days'     => $totalDays,
                'booked_nights'  => $bookedNights,
                'occupancy_rate' => $totalDays > 0 ? round(($bookedNights / $totalDays) * 100, 1) : 0,
                'bookings_count' => $villa->bookings->count(),
            ];
        });

        return response()->json(['from' => $from, 'to' => $to, 'data' => $villas]);
    }

    public function revenue(Request $request)
    {
        $from     = $request->get('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to       = $request->get('to', Carbon::now()->format('Y-m-d'));
        $groupBy  = $request->get('group_by', 'month');

        $format = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'year'  => '%Y',
            default => '%Y-%m',
        };

        $data = Booking::select(
                DB::raw("DATE_FORMAT(check_in, '{$format}') as period"),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(paid_amount) as total_paid'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('SUM(nights) as total_nights')
            )
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('check_in', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totals = Booking::whereNotIn('status', ['cancelled'])
            ->whereBetween('check_in', [$from, $to])
            ->selectRaw('SUM(total_amount) as total_revenue, SUM(paid_amount) as total_paid, COUNT(*) as bookings_count')
            ->first();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data, 'totals' => $totals]);
    }

    public function villaPerformance(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->get('to', Carbon::now()->format('Y-m-d'));

        $data = Villa::with('owner')
            ->withCount(['bookings as total_bookings' => fn($q) => $q->whereNotIn('status', ['cancelled'])->whereBetween('check_in', [$from, $to])])
            ->withCount(['bookings as cancelled_bookings' => fn($q) => $q->where('status', 'cancelled')->whereBetween('check_in', [$from, $to])])
            ->withSum(['bookings as total_revenue' => fn($q) => $q->whereNotIn('status', ['cancelled'])->whereBetween('check_in', [$from, $to])], 'total_amount')
            ->withSum(['bookings as total_nights' => fn($q) => $q->whereNotIn('status', ['cancelled'])->whereBetween('check_in', [$from, $to])], 'nights')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function userPerformance(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', Carbon::now()->format('Y-m-d'));

        $data = User::withCount(['bookings as total_bookings' => fn($q) => $q->whereNotIn('status', ['cancelled'])->whereBetween('check_in', [$from, $to])])
            ->withCount(['bookings as cancelled_bookings' => fn($q) => $q->where('status', 'cancelled')->whereBetween('check_in', [$from, $to])])
            ->withSum(['bookings as total_revenue' => fn($q) => $q->whereNotIn('status', ['cancelled'])->whereBetween('check_in', [$from, $to])], 'total_amount')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($user, $index) {
                $user->rank = $index + 1;
                return $user;
            });

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function bookingsSummary(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', Carbon::now()->format('Y-m-d'));

        $summary = Booking::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $summary]);
    }
}
