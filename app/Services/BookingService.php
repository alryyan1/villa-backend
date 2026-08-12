<?php

namespace App\Services;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Villa;
use Carbon\Carbon;

class BookingService
{
    /**
     * Shared booking-creation logic used by both the staff BookingController
     * and the owner-portal BookingController. Runs all business-rule guards
     * (villa/guest contact info, contract status, availability, ...),
     * computes nights/total, creates the Booking (+ optional advance
     * payment), and returns it loaded with villa.owner/guest/user.
     *
     * $validated must already have passed request-level validation; this
     * method only enforces business rules, not field presence/type.
     *
     * @throws BookingValidationException  on any business-rule guard failure (→ 422).
     */
    public function createBooking(array $validated, User $user): Booking
    {
        $isOwnerBooking = (bool) ($validated['is_owner'] ?? false);
        $validated['is_owner'] = $isOwnerBooking;

        $villa = Villa::with('owner')->findOrFail($validated['villa_id']);
        $guest = Guest::findOrFail($validated['guest_id']);

        if (!$villa->owner || !($villa->owner->whatsapp_number ?? $villa->owner->phone)) {
            throw new BookingValidationException("This villa's owner has no phone number on file. Add one before creating a booking.");
        }

        if (!$guest->phone) {
            throw new BookingValidationException('This guest has no phone number on file. Add one before creating a booking.');
        }

        if ((float) $villa->price_per_night <= 0) {
            throw new BookingValidationException('This villa has no price set. Set its price from the Villas page before creating a booking.');
        }

        if (!$villa->contract_active) {
            throw new BookingValidationException('This villa does not have an active management contract and cannot be booked.');
        }

        if ($villa->status === 'maintenance') {
            throw new BookingValidationException('This villa is under maintenance and cannot be booked.');
        }

        if ($villa->status === 'blocked') {
            throw new BookingValidationException('This villa is blocked and cannot be booked.');
        }

        if (
            Setting::get('enforce_contract_end_date', '1') === '1'
            && $villa->contract_end_date
            && $validated['check_out'] > $villa->contract_end_date->format('Y-m-d')
        ) {
            throw new BookingValidationException('The checkout date is after this villa\'s management contract ends on ' . $villa->contract_end_date->format('Y-m-d') . '.');
        }

        if (!$this->checkAvailability($validated['villa_id'], $validated['check_in'], $validated['check_out'])) {
            throw new BookingValidationException('The villa is already booked for this period.');
        }

        $nights        = $this->calculateNights($validated['check_in'], $validated['check_out']);
        $effectiveRate = $validated['price_per_night'] ?? $villa->price_per_night;

        $validated['user_id']         = $user->id;
        $validated['nights']          = $nights;
        $validated['original_nights'] = $nights;
        $validated['total_amount']    = round($effectiveRate * $nights, 3);
        $validated['status']          = $validated['status'] ?? 'confirmed';

        $bookingData = collect($validated)->except(['advance_amount', 'advance_method', 'price_per_night', 'progress_token'])->all();
        $booking = Booking::create($bookingData);

        if (!empty($validated['advance_amount'])) {
            $booking->payments()->create([
                'amount'       => $validated['advance_amount'],
                'payment_date' => now()->format('Y-m-d'),
                'method'       => $validated['advance_method'],
                'user_id'      => $user->id,
            ]);
            $this->updatePaymentStatus($booking);
            $booking->refresh();
        }

        $booking->load(['villa.owner', 'guest', 'user']);

        return $booking;
    }

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
