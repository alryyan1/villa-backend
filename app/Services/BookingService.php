<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Villa;
use Carbon\Carbon;

class BookingService
{
    public function checkAvailability(int $villaId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $query = Booking::where('villa_id', $villaId)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                  ->orWhereBetween('check_out', [$checkIn, $checkOut])
                  ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('check_in', '<=', $checkIn)
                         ->where('check_out', '>=', $checkOut);
                  });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->count() === 0;
    }

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
