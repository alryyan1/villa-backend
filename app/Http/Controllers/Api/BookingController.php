<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogService;
use App\Services\BookingService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private WhatsAppService $whatsAppService
    ) {}

    public function index(Request $request)
    {
        $query = Booking::with(['villa', 'guest', 'user']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->villa_id) {
            $query->where('villa_id', $request->villa_id);
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
            'advance_amount'   => 'nullable|numeric|min:0.01',
            'advance_method'   => 'nullable|in:cash,card,bank_transfer|required_with:advance_amount',
        ]);

        if (!$this->bookingService->checkAvailability($validated['villa_id'], $validated['check_in'], $validated['check_out'])) {
            return response()->json(['message' => 'The villa is already booked for this period.'], 422);
        }

        $villa  = \App\Models\Villa::findOrFail($validated['villa_id']);
        $nights = $this->bookingService->calculateNights($validated['check_in'], $validated['check_out']);

        $validated['user_id']      = $request->user()->id;
        $validated['nights']       = $nights;
        $validated['total_amount'] = $this->bookingService->calculateTotal($villa, $nights);
        $validated['status']       = $validated['status'] ?? 'confirmed';

        $bookingData = collect($validated)->except(['advance_amount', 'advance_method'])->all();
        $booking = Booking::create($bookingData);

        if (!empty($validated['advance_amount'])) {
            $booking->payments()->create([
                'amount'       => $validated['advance_amount'],
                'payment_date' => now()->format('Y-m-d'),
                'method'       => $validated['advance_method'],
            ]);
            $this->bookingService->updatePaymentStatus($booking);
            $booking->refresh();
        }

        $booking->load(['villa.owner', 'guest', 'user']);

        ActivityLogService::log('create_booking', 'Booking', $booking->id, [
            'villa' => $villa->name,
            'guest' => $booking->guest->name,
        ]);

        $this->whatsAppService->notifyBookingCreated($booking);

        return response()->json($booking, 201);
    }

    public function show(Booking $booking)
    {
        return response()->json($booking->load(['villa.owner', 'guest', 'user', 'payments']));
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
            $this->whatsAppService->notifyBookingUpdated($booking);
        }

        return response()->json($booking);
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
