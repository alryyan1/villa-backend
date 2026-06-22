<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

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
