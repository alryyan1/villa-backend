<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ActivityLogService;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    public function index(Booking $booking)
    {
        return response()->json($booking->payments()->with('user')->orderByDesc('payment_date')->get());
    }

    public function store(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method'       => 'in:cash,card,bank_transfer',
            'notes'        => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        $payment = $booking->payments()->create($validated);
        $payment->load('user');
        $this->bookingService->updatePaymentStatus($booking);

        ActivityLogService::log('add_payment', 'Payment', $payment->id, [
            'booking_id' => $booking->id,
            'amount'     => $validated['amount'],
        ]);

        return response()->json([
            'payment' => $payment,
            'booking' => $booking->fresh(),
        ], 201);
    }
}
