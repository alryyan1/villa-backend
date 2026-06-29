<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function __construct(private WhatsAppService $whatsAppService) {}

    public function whatsappPhoneInfo()
    {
        $info = (new WhatsAppCloudApiService())->getPhoneNumberInfo();
        return response()->json($info);
    }

    public function whatsappTest(Request $request)
    {
        $request->validate(['phone' => 'required|string|min:7|max:20']);

        $api    = new WhatsAppCloudApiService();
        $result = $api->sendTemplateMessage(
            $request->phone,
            'hello_world',
            'en_US',
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function summary()
    {
        $today = Carbon::today();

        $contractsExpired = Villa::whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', $today)
            ->select('id', 'name', 'contract_end_date')
            ->get()
            ->map(fn($v) => [
                'id'      => $v->id,
                'label'   => $v->name,
                'detail'  => 'Expired ' . Carbon::parse($v->contract_end_date)->format('d M Y'),
            ]);

        $contractsExpiring = Villa::whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '>=', $today)
            ->whereDate('contract_end_date', '<=', $today->copy()->addDays(30))
            ->select('id', 'name', 'contract_end_date')
            ->get()
            ->map(fn($v) => [
                'id'      => $v->id,
                'label'   => $v->name,
                'detail'  => 'Expires ' . Carbon::parse($v->contract_end_date)->format('d M Y')
                             . ' (' . Carbon::parse($v->contract_end_date)->diffInDays($today) . 'd left)',
            ]);

        $checkinsToday = Booking::with(['villa:id,name', 'guest:id,name'])
            ->whereDate('check_in', $today)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get()
            ->map(fn($b) => [
                'id'     => $b->id,
                'label'  => ($b->guest->name ?? '—') . ' → ' . ($b->villa->name ?? '—'),
                'detail' => 'Check-in today',
            ]);

        $checkoutsToday = Booking::with(['villa:id,name', 'guest:id,name'])
            ->whereDate('check_out', $today)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get()
            ->map(fn($b) => [
                'id'     => $b->id,
                'label'  => ($b->guest->name ?? '—') . ' → ' . ($b->villa->name ?? '—'),
                'detail' => 'Check-out today',
            ]);

        $pendingBookings = Booking::with(['villa:id,name', 'guest:id,name'])
            ->where('status', 'pending')
            ->whereDate('check_in', '>=', $today)
            ->orderBy('check_in')
            ->get()
            ->map(fn($b) => [
                'id'     => $b->id,
                'label'  => ($b->guest->name ?? '—') . ' → ' . ($b->villa->name ?? '—'),
                'detail' => 'Check-in ' . $b->check_in->format('d M Y'),
            ]);

        return response()->json([
            'contracts_expired'  => $contractsExpired,
            'contracts_expiring' => $contractsExpiring,
            'checkins_today'     => $checkinsToday,
            'checkouts_today'    => $checkoutsToday,
            'pending_bookings'   => $pendingBookings,
            'total'              => $contractsExpired->count()
                                  + $contractsExpiring->count()
                                  + $checkinsToday->count()
                                  + $checkoutsToday->count()
                                  + $pendingBookings->count(),
        ]);
    }

    public function sendWhatsApp(Request $request)
    {
        $request->validate([
            'phone'   => 'required|string',
            'message' => 'required|string',
        ]);

        $sent = $this->whatsAppService->sendMessage($request->phone, $request->message);

        return response()->json([
            'sent'    => $sent,
            'message' => $sent ? 'Message sent successfully.' : 'Failed to send (check UltraMsg configuration).',
        ]);
    }
}
