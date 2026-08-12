<?php

namespace App\Http\Controllers\Api\OwnerPortal;

use App\Exceptions\BookingValidationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogService;
use App\Services\BookingService;
use App\Services\FirebaseService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private WhatsAppService $whatsAppService,
        private FirebaseService $firebaseService
    ) {}

    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        $query = Booking::with(['villa', 'guest'])
            ->withSum('payments', 'amount')
            ->whereIn('villa_id', $villaIds);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->villa_id) {
            $query->where('villa_id', $request->villa_id);
        }
        if ($request->from) {
            $query->where('check_in', '>=', $request->from);
        }
        if ($request->to) {
            $query->where('check_out', '<=', $request->to);
        }

        $bookings = $query->orderByDesc('id')->paginate(20);
        $bookings->getCollection()->transform(fn (Booking $b) => $this->withCommission($b));

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking)
    {
        $villaIds = $request->user()->ownedVillaIds();

        if (!in_array($booking->villa_id, $villaIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $booking->load(['villa', 'guest', 'payments']);

        return response()->json($this->withCommission($booking));
    }

    public function store(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        if (empty($villaIds)) {
            return response()->json(['message' => 'No villas are linked to your account.'], 422);
        }

        $validated = $request->validate([
            'villa_id'      => ['required', Rule::in($villaIds)],
            'guest_id'      => 'required|exists:guests,id',
            'num_guests'    => 'required|integer|min:1',
            'check_in'      => 'required|date',
            'check_in_time' => 'nullable|string|max:5',
            'check_out'     => 'required|date|after:check_in',
            'notes'         => 'nullable|string',
        ]);

        // Never trust client input for these — always forced server-side.
        $validated['status']   = 'pending';
        $validated['is_owner'] = true;

        try {
            $booking = $this->bookingService->createBooking($validated, $request->user());
        } catch (BookingValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLogService::log('create_booking', 'Booking', $booking->id, [
            'villa' => $booking->villa->name,
            'guest' => $booking->guest->name,
        ]);

        try {
            $this->firebaseService->generateAndStoreBookingConfirmation($booking);
        } catch (\Throwable $e) {
            Log::error("Firebase confirmation PDF failed for booking {$booking->id}: " . $e->getMessage());
        }

        $whatsapp = $this->whatsAppService->notifyBookingCreated($booking);

        return response()->json(['booking' => $this->withCommission($booking), 'whatsapp' => $whatsapp], 201);
    }

    public function checkAvailability(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        $validated = $request->validate([
            'villa_id'  => ['required', Rule::in($villaIds)],
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        $available = $this->bookingService->checkAvailability(
            $validated['villa_id'],
            $validated['check_in'],
            $validated['check_out']
        );

        $conflicts = [];
        if (!$available) {
            $conflicts = $this->bookingService
                ->findConflicts($validated['villa_id'], $validated['check_in'], $validated['check_out'])
                ->orderBy('check_in')
                ->get(['id', 'check_in', 'check_out', 'status'])
                ->map(fn (Booking $b) => [
                    'id'        => $b->id,
                    'check_in'  => $b->check_in->format('Y-m-d'),
                    'check_out' => $b->check_out->format('Y-m-d'),
                    'status'    => $b->status,
                ]);
        }

        return response()->json(['available' => $available, 'conflicts' => $conflicts]);
    }

    /**
     * Adds the commission/net breakdown as seen by WhatsAppService's owner
     * notification template: 5% commission on staff-created bookings, 0 on
     * the owner's own self-bookings (is_owner=true) — net = total - commission.
     */
    private function withCommission(Booking $booking): Booking
    {
        $total      = (float) $booking->total_amount;
        $commission = $booking->is_owner ? 0.0 : round($total * 0.05, 3);
        $net        = $total - $commission;

        $booking->setAttribute('commission_amount', $commission);
        $booking->setAttribute('net_amount', $net);

        return $booking;
    }
}
