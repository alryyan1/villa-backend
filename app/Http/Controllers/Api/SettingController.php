<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'whatsapp_enabled',
        'owner_notifications_enabled',
        'booking_confirmation_notify_enabled',
        'firebase_upload_enabled',
        'enforce_contract_end_date',
        'owner_booking_template',
        'owner_booking_template_lang',
        'guest_booking_template',
        'guest_booking_template_lang',
        'guest_pending_booking_template',
        'guest_pending_booking_template_lang',
        'guest_checkout_reminder_template',
        'guest_checkout_reminder_template_lang',
        'user_booking_template',
        'user_booking_template_lang',
        'owner_self_booking_template',
        'owner_self_booking_template_lang',
        'owner_booking_template_has_button',
        'guest_booking_template_has_button',
        'guest_pending_booking_template_has_button',
        'user_booking_template_has_button',
        'owner_self_booking_template_has_button',
        'reception_phone_1',
        'reception_phone_2',
        'stamp_image',
    ];

    public function index()
    {
        $settings = Setting::whereIn('key', self::ALLOWED_KEYS)->pluck('value', 'key');

        foreach (self::ALLOWED_KEYS as $key) {
            if (!isset($settings[$key])) {
                $settings[$key] = null;
            }
        }

        $settings['stamp_image_url'] = $settings['stamp_image']
            ? Storage::disk('public')->url($settings['stamp_image'])
            : null;

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_enabled'            => 'sometimes|boolean',
            'owner_notifications_enabled' => 'sometimes|boolean',
            'booking_confirmation_notify_enabled' => 'sometimes|boolean',
            'firebase_upload_enabled'     => 'sometimes|boolean',
            'enforce_contract_end_date'   => 'sometimes|boolean',
            'owner_booking_template'      => 'sometimes|nullable|string|max:100',
            'owner_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'guest_booking_template'      => 'sometimes|nullable|string|max:100',
            'guest_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'guest_pending_booking_template'      => 'sometimes|nullable|string|max:100',
            'guest_pending_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'guest_checkout_reminder_template'      => 'sometimes|nullable|string|max:100',
            'guest_checkout_reminder_template_lang' => 'sometimes|nullable|string|max:20',
            'user_booking_template'      => 'sometimes|nullable|string|max:100',
            'user_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'owner_self_booking_template'      => 'sometimes|nullable|string|max:100',
            'owner_self_booking_template_lang' => 'sometimes|nullable|string|max:20',
            'owner_booking_template_has_button'     => 'sometimes|boolean',
            'guest_booking_template_has_button'     => 'sometimes|boolean',
            'guest_pending_booking_template_has_button' => 'sometimes|boolean',
            'user_booking_template_has_button'      => 'sometimes|boolean',
            'owner_self_booking_template_has_button' => 'sometimes|boolean',
            'reception_phone_1'           => 'sometimes|nullable|string|max:30',
            'reception_phone_2'           => 'sometimes|nullable|string|max:30',
        ]);

        foreach ($validated as $key => $value) {
            if (in_array($key, ['whatsapp_enabled', 'owner_notifications_enabled', 'booking_confirmation_notify_enabled', 'firebase_upload_enabled', 'enforce_contract_end_date', 'owner_booking_template_has_button', 'guest_booking_template_has_button', 'guest_pending_booking_template_has_button', 'user_booking_template_has_button', 'owner_self_booking_template_has_button'], true)) {
                Setting::set($key, $value ? '1' : '0');
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    public function uploadStamp(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $oldPath = Setting::get('stamp_image');
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->store('settings', 'public');
        Setting::set('stamp_image', $path);

        return response()->json([
            'message' => 'Stamp image uploaded.',
            'stamp_image' => $path,
            'stamp_image_url' => Storage::disk('public')->url($path),
        ]);
    }
}
