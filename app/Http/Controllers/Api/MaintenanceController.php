<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use Carbon\Carbon;

class MaintenanceController extends Controller
{
    public function turnover()
    {
        $today     = now()->toDateString();
        $lookback  = now()->subDays(3)->toDateString();
        $lookahead = now()->addDays(7)->toDateString();

        $today  = now()->toDateString();
        $villas = Villa::whereDate('contract_start_date', '<=', $today)
                       ->whereDate('contract_end_date', '>=', $today)
                       ->get();

        $recentCheckouts = Booking::with('guest')
            ->whereBetween('check_out', [$lookback, $today])
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->groupBy('villa_id');

        $upcomingCheckins = Booking::with('guest')
            ->where('check_in', '>=', $today)
            ->where('check_in', '<=', $lookahead)
            ->where('status', 'confirmed')
            ->orderBy('check_in')
            ->get()
            ->groupBy('villa_id');

        $result = $villas->map(function ($villa) use ($recentCheckouts, $upcomingCheckins) {
            $checkout = $recentCheckouts->get($villa->id)?->sortByDesc('check_out')->first();
            $checkin  = $upcomingCheckins->get($villa->id)?->sortBy('check_in')->first();

            $gapHours = null;
            $isUrgent = false;
            if ($checkout && $checkin) {
                $gapHours = Carbon::parse($checkout->check_out)->diffInHours(Carbon::parse($checkin->check_in));
                $isUrgent = $gapHours < 24;
            }

            return [
                'villa'         => ['id' => $villa->id, 'name' => $villa->name, 'status' => $villa->status, 'num_rooms' => $villa->num_rooms],
                'last_checkout' => $checkout ? [
                    'id'             => $checkout->id,
                    'check_out'      => $checkout->check_out->format('Y-m-d'),
                    'guest_name'     => $checkout->guest->name,
                    'checked_out_at' => $checkout->checked_out_at,
                ] : null,
                'next_checkin'  => $checkin ? [
                    'id'            => $checkin->id,
                    'check_in'      => $checkin->check_in->format('Y-m-d'),
                    'check_in_time' => $checkin->check_in_time,
                    'guest_name'    => $checkin->guest->name,
                    'num_guests'    => $checkin->num_guests ?? 1,
                ] : null,
                'gap_hours' => $gapHours,
                'is_urgent' => $isUrgent,
            ];
        })
        ->filter(fn($item) =>
            $item['last_checkout'] !== null ||
            $item['next_checkin']  !== null ||
            $item['villa']['status'] === 'maintenance'
        )
        ->values();

        return response()->json($result);
    }
}
