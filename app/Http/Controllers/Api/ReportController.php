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
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->endOfMonth()->format('Y-m-d'));

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
        $from    = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to      = $request->input('to', Carbon::now()->format('Y-m-d'));
        $groupBy = $request->input('group_by', 'month');

        $format = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'year'  => '%Y',
            default => '%Y-%m',
        };

        // paid_amount no longer stored on bookings — derive from payments table
        $data = Booking::select(
                DB::raw("DATE_FORMAT(bookings.check_in, '{$format}') as period"),
                DB::raw('SUM(bookings.total_amount) as total_revenue'),
                DB::raw('COALESCE(SUM(p.paid), 0) as total_paid'),
                DB::raw('COUNT(DISTINCT bookings.id) as bookings_count'),
                DB::raw('SUM(bookings.nights) as total_nights')
            )
            ->leftJoin(
                DB::raw('(SELECT booking_id, SUM(amount) as paid FROM payments GROUP BY booking_id) as p'),
                'p.booking_id', '=', 'bookings.id'
            )
            ->whereNotIn('bookings.status', ['cancelled'])
            ->whereBetween('bookings.check_in', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totalRevenue = Booking::whereNotIn('status', ['cancelled'])
            ->whereBetween('check_in', [$from, $to])
            ->sum('total_amount');

        $totalPaid = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereNotIn('bookings.status', ['cancelled'])
            ->whereBetween('bookings.check_in', [$from, $to])
            ->sum('payments.amount');

        $bookingsCount = Booking::whereNotIn('status', ['cancelled'])
            ->whereBetween('check_in', [$from, $to])
            ->count();

        $totals = [
            'total_revenue'  => (float) $totalRevenue,
            'total_paid'     => (float) $totalPaid,
            'bookings_count' => $bookingsCount,
        ];

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data, 'totals' => $totals]);
    }

    public function villaPerformance(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

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
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

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

    public function paymentMethods(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $rows = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('guests',   'bookings.guest_id',   '=', 'guests.id')
            ->select(
                'payments.method',
                DB::raw('SUM(payments.amount) as total_collected'),
                DB::raw('COUNT(payments.id) as payments_count'),
                DB::raw('COUNT(DISTINCT payments.booking_id) as bookings_count')
            )
            ->whereBetween('payments.payment_date', [$from, $to])
            ->groupBy('payments.method')
            ->orderByDesc('total_collected')
            ->get();

        $total = $rows->sum('total_collected');

        $data = $rows->map(fn($r) => [
            'method'           => $r->method,
            'total_collected'  => (float) $r->total_collected,
            'payments_count'   => (int)   $r->payments_count,
            'bookings_count'   => (int)   $r->bookings_count,
            'percentage'       => $total > 0 ? round(($r->total_collected / $total) * 100, 1) : 0,
        ]);

        return response()->json([
            'from'  => $from,
            'to'    => $to,
            'data'  => $data,
            'total' => (float) $total,
        ]);
    }

    public function bookingsSummary(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $summary = Booking::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $summary]);
    }
}
