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
        'guest_checkout_reminder_template',
        'guest_checkout_reminder_template_lang',
        'owner_booking_template_has_button',
        'guest_booking_template_has_button',
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
            'guest_checkout_reminder_template'      => 'sometimes|nullable|string|max:100',
            'guest_checkout_reminder_template_lang' => 'sometimes|nullable|string|max:20',
            'owner_booking_template_has_button'     => 'sometimes|boolean',
            'guest_booking_template_has_button'     => 'sometimes|boolean',
        ]);

        foreach ($validated as $key => $value) {
            if (in_array($key, ['whatsapp_enabled', 'owner_notifications_enabled', 'owner_booking_template_has_button', 'guest_booking_template_has_button'], true)) {
                Setting::set($key, $value ? '1' : '0');
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        return response()->json(['message' => 'Settings saved.']);
    }
}
