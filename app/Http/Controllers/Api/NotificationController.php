<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private WhatsAppService $whatsAppService) {}

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
