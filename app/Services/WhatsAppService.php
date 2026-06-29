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

    public function notifyBookingCreated(Booking $booking): array
    {
        $booking->load(['villa.owner', 'guest', 'user']);

        $results = ['owner' => null, 'tenant' => null];

        // Owner template
        if (Setting::get('owner_notifications_enabled', '1') === '1') {
            $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
            if ($ownerPhone) {
                Log::info("WhatsApp: sending booking notification to owner: {$ownerPhone}");
                $results['owner'] = $this->sendOwnerBookingTemplate($booking, $ownerPhone);
            }
        }

        // Tenant template
        if (Setting::get('tenant_notifications_enabled', '1') !== '0' && $booking->guest->phone) {
            Log::info("WhatsApp: sending booking notification to tenant: {$booking->guest->phone}");
            $results['tenant'] = $this->sendTenantBookingTemplate($booking, $booking->guest->phone);
        }

        return $results;
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

    private function sendOwnerBookingTemplate(Booking $booking, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('owner_booking_template', '');
        $templateLang = Setting::get('owner_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: owner booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for owner: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $total      = (float) $booking->total_amount;
        $commission = $total * 0.05;
        $net        = $total - $commission;

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => (string) $booking->id],
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
                ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
                ['type' => 'text', 'text' => number_format($total, 3)],
                ['type' => 'text', 'text' => number_format($commission, 3)],
                ['type' => 'text', 'text' => number_format($net, 3)],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components);

        if (!$result['success']) {
            Log::error('WhatsApp owner template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendTenantBookingTemplate(Booking $booking, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('guest_booking_template', '');
        $templateLang = Setting::get('guest_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: tenant booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for tenant: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $receptionPhone1 = Setting::get('reception_phone_1', '76767769');
        $receptionPhone2 = Setting::get('reception_phone_2', '76767768');

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => (string) $booking->id],
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
                ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
                ['type' => 'text', 'text' => number_format((float) $booking->total_amount, 3)],
                ['type' => 'text', 'text' => $receptionPhone1],
                ['type' => 'text', 'text' => $receptionPhone2],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components);

        if (!$result['success']) {
            Log::error('WhatsApp tenant template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendToOwnerAndManagement(Booking $booking, string $message): void
    {
        if (Setting::get('owner_notifications_enabled', '1') !== '1') return;

        $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
        if ($ownerPhone) {
            $this->sendMessage($ownerPhone, $message);
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
