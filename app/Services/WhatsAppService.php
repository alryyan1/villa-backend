<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private WhatsAppCloudApiService $api;

    public function __construct()
    {
        $this->api = new WhatsAppCloudApiService();
    }

    public function isEnabled(): bool
    {
        return Setting::get('whatsapp_enabled', '1') === '1';
    }

    public function sendMessage(string $phone, string $message): bool
    {
        if (!$this->isEnabled()) {
            Log::info("WhatsApp (disabled): to={$phone}, msg={$message}");
            return false;
        }

        if (!$this->api->isConfigured()) {
            Log::info("WhatsApp (not configured): to={$phone}, msg={$message}");
            return false;
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone number: {$phone}");
            return false;
        }

        $result = $this->api->sendTextMessage($normalized, $message);
        return $result['success'];
    }

    public function notifyBookingCreated(Booking $booking): void
    {
        $booking->load(['villa.owner', 'guest', 'user']);

     //   $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'Created'));

        $guestPhone = $booking->guest->phone;
        if ($guestPhone) {
            $this->sendMessage($guestPhone, $this->buildGuestConfirmationMessage($booking));
        }

        $this->sendBookingAlertTemplate($booking);
    }

    public function notifyBookingUpdated(Booking $booking): void
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'Updated'));
    }

    public function notifyBookingCancelled(Booking $booking): void
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'Cancelled'));

        $guestPhone = $booking->guest->phone;
        if ($guestPhone) {
            $this->sendMessage($guestPhone,
                "Dear {$booking->guest->name},\n\n"
                . "We would like to inform you that your booking for villa *{$booking->villa->name}* "
                . "on {$booking->check_in->format('Y-m-d')} has been *cancelled*.\n\n"
                . "Please contact us if you have any questions. Thank you 🏡"
            );
        }
    }

    // ── Message builders ────────────────────────────────────────────────────

    private function buildOwnerMessage(Booking $booking, string $action): string
    {
        $checkInTime = $booking->check_in_time ? " at {$booking->check_in_time}" : '';
        $numGuests   = $booking->num_guests ?? 1;
        $numRooms    = $booking->villa->num_rooms ?? '—';

        return "🏡 *Booking Notification — {$action}*\n\n"
            . "Villa: {$booking->villa->name} ({$numRooms} rooms)\n"
            . "Guest: {$booking->guest->name}\n"
            . "Guests: {$numGuests}\n"
            . "Check-in: {$booking->check_in->format('Y-m-d')}{$checkInTime}\n"
            . "Check-out: {$booking->check_out->format('Y-m-d')}\n"
            . "Nights: {$booking->nights}\n"
            . "Total: " . number_format((float) $booking->total_amount, 3) . " OMR\n"
            . "Status: {$this->translateStatus($booking->status)}\n"
            . "By: {$booking->user->name}";
    }

    private function buildGuestConfirmationMessage(Booking $booking): string
    {
        $checkInTime = $booking->check_in_time
            ? "at {$booking->check_in_time}"
            : 'between 10:00 AM and 2:00 PM';
        $numRooms = $booking->villa->num_rooms ?? '—';

        $usageRules = config('services.villa.usage_rules',
            "• No visitors outside the registered guests.\n"
            . "• No smoking inside the villa.\n"
            . "• Please keep the premises clean.\n"
            . "• Check-out time is 12:00 PM.\n"
            . "• Any damages are the tenant's responsibility."
        );

        return "Welcome, {$booking->guest->name}! 🌟\n\n"
            . "*Booking Confirmation — Villa {$booking->villa->name}*\n\n"
            . "📋 *Booking Details:*\n"
            . "• Villa: {$booking->villa->name} ({$numRooms} rooms)\n"
            . "• Check-in: {$booking->check_in->format('Y-m-d')}\n"
            . "• Check-in time: {$checkInTime}\n"
            . "• Check-out: {$booking->check_out->format('Y-m-d')}\n"
            . "• Nights: {$booking->nights}\n"
            . "• Total: " . number_format((float) $booking->total_amount, 3) . " OMR\n\n"
            . "📌 *Usage Rules:*\n"
            . $usageRules . "\n\n"
            . "We wish you a pleasant stay! 🏡";
    }

    private function sendBookingAlertTemplate(Booking $booking): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $templateName = config('services.whatsapp_cloud.booking_template_name', 'booking');
        $templateLang = config('services.whatsapp_cloud.booking_template_lang', 'en');
        $alertNumber  = config('services.whatsapp_cloud.booking_alert_number', '96878622990');

        if (empty($templateName)) {
            Log::info('WhatsApp booking alert template not configured — skipping.');
            return;
        }

        $components = [
            [
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $booking->villa->name],
                    ['type' => 'text', 'text' => $booking->guest->name],
                    ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
                    ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
                    ['type' => 'text', 'text' => (string) ($booking->num_guests ?? 1)],
                    ['type' => 'text', 'text' => number_format((float) $booking->total_amount, 3) . ' OMR'],
                ],
            ],
        ];

        $result = $this->api->sendTemplateMessage($alertNumber, $templateName, $templateLang, $components);

        if (!$result['success']) {
            Log::error('WhatsApp booking alert template failed: ' . ($result['error'] ?? 'unknown'));
        }
    }

    private function sendToOwnerAndManagement(Booking $booking, string $message): void
    {
        $ownerNotificationsEnabled = Setting::get('owner_notifications_enabled', '1') === '1';
        $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
        if ($ownerPhone && $ownerNotificationsEnabled) {
            Log::info("WhatsApp: sending to owner {$booking->villa->owner->name} ({$ownerPhone})");
            // $this->sendMessage($ownerPhone, $message);
        }

        $recipients = json_decode(Setting::get('whatsapp_recipients', '[]'), true) ?? [];

        // Fall back to config if no DB recipients are set
        if (empty($recipients)) {
            $mgmt = config('services.whatsapp_cloud.management_number', '');
            if ($mgmt) {
                $recipients[] = $mgmt;
            }
        }

        foreach ($recipients as $phone) {
            if ($phone && $phone !== $ownerPhone) {
                $this->sendMessage($phone, $message);
            }
        }
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'pending'   => 'Pending',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            default     => $status,
        };
    }
}
