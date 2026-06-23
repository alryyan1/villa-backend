<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'whatsapp_enabled',
        'owner_notifications_enabled',
        'owner_booking_template',
        'owner_booking_template_lang',
        'guest_booking_template',
        'guest_booking_template_lang',
    ];

    public function index()
    {
        $settings = Setting::whereIn('key', self::ALLOWED_KEYS)->pluck('value', 'key');

        foreach (self::ALLOWED_KEYS as $key) {
            if (!isset($settings[$key])) {
                $settings[$key] = null;
            }
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_enabled'            => 'sometimes|boolean',
            'owner_notifications_enabled' => 'sometimes|boolean',
            'owner_booking_template'      => 'sometimes|nullable|string|max:100',
            'owner_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'guest_booking_template'      => 'sometimes|nullable|string|max:100',
            'guest_booking_template_lang' => 'sometimes|nullable|string|max:20',
        ]);

        foreach ($validated as $key => $value) {
            if (in_array($key, ['whatsapp_enabled', 'owner_notifications_enabled'], true)) {
                Setting::set($key, $value ? '1' : '0');
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        return response()->json(['message' => 'Settings saved.']);
    }
}
