<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogService;
use App\Services\BookingService;
use App\Services\FirebaseService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private WhatsAppService $whatsAppService,
        private FirebaseService $firebaseService
    ) {}

    public function index(Request $request)
    {
        $query = Booking::with(['villa', 'guest', 'user'])->withSum('payments', 'amount');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->villa_id) {
            $query->where('villa_id', $request->villa_id);
        }
        if ($request->guest_id) {
            $query->where('guest_id', $request->guest_id);
        }
        if ($request->guest_name) {
            $query->whereHas('guest', fn($q) => $q->where('name', 'like', "%{$request->guest_name}%"));
        }
        if ($request->from) {
            $query->where('check_in', '>=', $request->from);
        }
        if ($request->to) {
            $query->where('check_out', '<=', $request->to);
        }
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        return response()->json($query->orderByDesc('id')->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'villa_id'         => 'required|exists:villas,id',
            'guest_id'         => 'required|exists:guests,id',
            'num_guests'       => 'required|integer|min:1',
            'check_in'         => 'required|date',
            'check_in_time'    => 'nullable|string|max:5',
            'check_out'        => 'required|date|after:check_in',
            'status'           => 'in:pending,confirmed,cancelled,completed',
            'notes'            => 'nullable|string',
            'payment_notes'    => 'nullable|string',
            'price_per_night'  => 'nullable|numeric|min:0.001',
            'advance_amount'   => 'nullable|numeric|min:0.01',
            'advance_method'   => 'nullable|in:cash,card,bank_transfer|required_with:advance_amount',
        ]);

        $villa = \App\Models\Villa::findOrFail($validated['villa_id']);

        if (!$villa->contract_active) {
            return response()->json(['message' => 'This villa does not have an active management contract and cannot be booked.'], 422);
        }

        if (!$this->bookingService->checkAvailability($validated['villa_id'], $validated['check_in'], $validated['check_out'])) {
            return response()->json(['message' => 'The villa is already booked for this period.'], 422);
        }
        $nights       = $this->bookingService->calculateNights($validated['check_in'], $validated['check_out']);
        $effectiveRate = $validated['price_per_night'] ?? $villa->price_per_night;

        $validated['user_id']      = $request->user()->id;
        $validated['nights']       = $nights;
        $validated['total_amount'] = round($effectiveRate * $nights, 3);
        $validated['status']       = $validated['status'] ?? 'confirmed';

        $bookingData = collect($validated)->except(['advance_amount', 'advance_method', 'price_per_night'])->all();
        $booking = Booking::create($bookingData);

        if (!empty($validated['advance_amount'])) {
            $booking->payments()->create([
                'amount'       => $validated['advance_amount'],
                'payment_date' => now()->format('Y-m-d'),
                'method'       => $validated['advance_method'],
                'user_id'      => $request->user()->id,
            ]);
            $this->bookingService->updatePaymentStatus($booking);
            $booking->refresh();
        }

        $booking->load(['villa.owner', 'guest', 'user']);

        ActivityLogService::log('create_booking', 'Booking', $booking->id, [
            'villa' => $villa->name,
            'guest' => $booking->guest->name,
        ]);

        try {
            $this->firebaseService->generateAndStoreBookingConfirmation($booking);
        } catch (\Throwable $e) {
            Log::error("Firebase confirmation PDF failed for booking {$booking->id}: " . $e->getMessage());
        }

        $whatsapp = $this->whatsAppService->notifyBookingCreated($booking);

        return response()->json(['booking' => $booking, 'whatsapp' => $whatsapp], 201);
    }

    public function show(Booking $booking)
    {
        return response()->json($booking->load(['villa.owner', 'guest', 'user', 'payments.user']));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'villa_id'            => 'sometimes|exists:villas,id',
            'guest_id'            => 'sometimes|exists:guests,id',
            'num_guests'          => 'sometimes|integer|min:1',
            'check_in'            => 'sometimes|date',
            'check_in_time'       => 'nullable|string|max:5',
            'check_out'           => 'sometimes|date|after:check_in',
            'status'              => 'sometimes|in:pending,confirmed,cancelled,completed',
            'notes'               => 'nullable|string',
            'payment_notes'       => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
        ]);

        $villaId  = $validated['villa_id'] ?? $booking->villa_id;
        $checkIn  = $validated['check_in']  ?? $booking->check_in->format('Y-m-d');
        $checkOut = $validated['check_out'] ?? $booking->check_out->format('Y-m-d');

        if (isset($validated['check_in']) || isset($validated['check_out']) || isset($validated['villa_id'])) {
            if (!$this->bookingService->checkAvailability($villaId, $checkIn, $checkOut, $booking->id)) {
                return response()->json(['message' => 'The villa is already booked for this period.'], 422);
            }
        }

        if (isset($validated['check_in']) || isset($validated['check_out'])) {
            $villa                     = \App\Models\Villa::find($villaId);
            $validated['nights']       = $this->bookingService->calculateNights($checkIn, $checkOut);
            $validated['total_amount'] = $this->bookingService->calculateTotal($villa, $validated['nights']);
        }

        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $validated['cancelled_at'] = now();
        }

        $booking->update($validated);
        $booking->load(['villa.owner', 'guest', 'user']);

        ActivityLogService::log('update_booking', 'Booking', $booking->id);

        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $this->whatsAppService->notifyBookingCancelled($booking);
        } else {
            try {
                $this->firebaseService->generateAndStoreBookingConfirmation($booking);
            } catch (\Throwable $e) {
                Log::error("Firebase confirmation PDF failed for booking {$booking->id}: " . $e->getMessage());
            }
            $this->whatsAppService->notifyBookingUpdated($booking);
        }

        return response()->json($booking);
    }

    public function confirmArrival(Booking $booking)
    {
        if ($booking->checked_in_at) {
            return response()->json(['message' => 'Already checked in.'], 422);
        }
        $booking->update(['checked_in_at' => now()]);
        ActivityLogService::log('confirm_arrival', 'Booking', $booking->id);
        return response()->json($booking->fresh());
    }

    public function confirmDeparture(Booking $booking)
    {
        if (!$booking->checked_in_at) {
            return response()->json(['message' => 'Must confirm arrival first.'], 422);
        }
        if ($booking->checked_out_at) {
            return response()->json(['message' => 'Already checked out.'], 422);
        }
        $booking->update(['checked_out_at' => now(), 'status' => 'completed']);
        ActivityLogService::log('confirm_departure', 'Booking', $booking->id);
        return response()->json($booking->fresh());
    }

    public function sendCheckoutReminder(Booking $booking)
    {
        $booking->load(['villa', 'guest']);
        $result = $this->whatsAppService->sendCheckoutReminder($booking);
        ActivityLogService::log('send_checkout_reminder', 'Booking', $booking->id);
        return response()->json($result);
    }

    public function destroy(Booking $booking)
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $this->whatsAppService->notifyBookingCancelled($booking);
        ActivityLogService::log('delete_booking', 'Booking', $booking->id);
        $booking->delete();
        return response()->json(['message' => 'Booking deleted successfully.']);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'villa_id'   => 'required|exists:villas,id',
            'check_in'   => 'required|date',
            'check_out'  => 'required|date|after:check_in',
            'booking_id' => 'nullable|exists:bookings,id',
        ]);

        $available = $this->bookingService->checkAvailability(
            $request->villa_id,
            $request->check_in,
            $request->check_out,
            $request->booking_id
        );

        return response()->json(['available' => $available]);
    }
}
