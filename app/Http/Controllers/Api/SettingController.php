<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = ['whatsapp_enabled', 'whatsapp_recipients', 'owner_notifications_enabled'];

    public function index()
    {
        $settings = Setting::whereIn('key', self::ALLOWED_KEYS)->pluck('value', 'key');

        foreach (self::ALLOWED_KEYS as $key) {
            if (!isset($settings[$key])) {
                $settings[$key] = null;
            }
        }

        // Decode recipients JSON so the frontend receives an array
        if (isset($settings['whatsapp_recipients'])) {
            $settings['whatsapp_recipients'] = json_decode($settings['whatsapp_recipients'], true) ?? [];
        } else {
            $settings['whatsapp_recipients'] = [];
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_enabled'            => 'sometimes|boolean',
            'owner_notifications_enabled' => 'sometimes|boolean',
            'whatsapp_recipients'         => 'sometimes|array',
            'whatsapp_recipients.*'       => 'string|max:20',
        ]);

        if (\array_key_exists('whatsapp_enabled', $validated)) {
            Setting::set('whatsapp_enabled', $validated['whatsapp_enabled'] ? '1' : '0');
        }

        if (\array_key_exists('owner_notifications_enabled', $validated)) {
            Setting::set('owner_notifications_enabled', $validated['owner_notifications_enabled'] ? '1' : '0');
        }

        if (\array_key_exists('whatsapp_recipients', $validated)) {
            $numbers = array_values(array_filter(array_map('trim', $validated['whatsapp_recipients'])));
            Setting::set('whatsapp_recipients', json_encode($numbers));
        }

        return response()->json(['message' => 'Settings saved.']);
    }
}
