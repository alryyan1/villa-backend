<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Villa;
use Carbon\Carbon;

class BookingService
{
    /**
     * Check whether a villa is available for the given date range.
     *
     * Returns true if no active (non-cancelled) booking overlaps the requested
     * period. The overlap test uses the standard half-open interval logic:
     *   existing.check_in  < requested.check_out
     *   existing.check_out > requested.check_in
     *-
     * Same-day turnovers are allowed: a guest checking out on June 27 does NOT
     * block a new booking that starts on June 27, because the strict inequalities
     * (< and >) exclude shared boundary dates from being treated as a conflict.
     *
     * @param  int         $villaId           The villa to check.
     * @param  string      $checkIn           Requested check-in date  (Y-m-d).
     * @param  string      $checkOut          Requested check-out date (Y-m-d).
     * @param  int|null    $excludeBookingId  Booking to ignore (used when editing an existing booking).
     * @return bool  True = available, false = conflict found.
     */
    public function checkAvailability(int $villaId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        return $this->findConflicts($villaId, $checkIn, $checkOut, $excludeBookingId)->count() === 0;
    }

    /**
     * Query for the non-cancelled bookings that overlap the given date range,
     * using the same half-open interval logic as checkAvailability().
     */
    public function findConflicts(int $villaId, string $checkIn, string $checkOut, ?int $excludeBookingId = null)
    {
        $query = Booking::where('villa_id', $villaId)
            ->whereNotIn('status', ['cancelled'])
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query;
    }

    /**
     * Calculate the number of nights between check-in and check-out.
     *
     * Uses Carbon's diffInDays which counts only whole days, so
     * June 27 → June 30 = 3 nights (not 4).
     *
     * @param  string  $checkIn   Check-in date  (Y-m-d).
     * @param  string  $checkOut  Check-out date (Y-m-d).
     * @return int  Number of nights.
     */
    public function calculateNights(string $checkIn, string $checkOut): int
    {
        return Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
    }

    public function calculateTotal(Villa $villa, int $nights): float
    {
        return $villa->price_per_night * $nights;
    }

    public function updatePaymentStatus(Booking $booking): void
    {
        $totalPaid = $booking->payments()->sum('amount');

        if ($totalPaid <= 0) {
            $booking->payment_status = 'unpaid';
        } elseif ($totalPaid < $booking->total_amount) {
            $booking->payment_status = 'partial';
        } else {
            $booking->payment_status = 'paid';
        }

        $booking->save();
    }
}
