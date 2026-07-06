<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppBotService;
use Illuminate\Http\Request;

class WhatsAppBotController extends Controller
{
    public function handleEvent(Request $request, WhatsAppBotService $bot)
    {
        $secret = config('services.whatsapp_cloud.relay_secret');

        if (empty($secret) || !hash_equals($secret, (string) $request->header('X-Relay-Secret'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'from'      => 'required|string',
            'action_id' => 'required|string',
        ]);

        $bot->handle($data['from'], $data['action_id']);

        return response()->json(['ok' => true]);
    }
}
