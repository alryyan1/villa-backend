<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private WhatsAppCloudApiService $api;

    public function __construct()
    {
        $this->api = new WhatsAppCloudApiService();
    }

    public function sendMessage(string $phone, string $message): bool
    {
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

        $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'إنشاء'));

        $guestPhone = $booking->guest->phone;
        if ($guestPhone) {
            $this->sendMessage($guestPhone, $this->buildGuestConfirmationMessage($booking));
        }
    }

    public function notifyBookingUpdated(Booking $booking): void
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'تعديل'));
    }

    public function notifyBookingCancelled(Booking $booking): void
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $this->sendToOwnerAndManagement($booking, $this->buildOwnerMessage($booking, 'إلغاء'));

        $guestPhone = $booking->guest->phone;
        if ($guestPhone) {
            $this->sendMessage($guestPhone,
                "عزيزنا {$booking->guest->name}،\n\n"
                . "نُعلمكم بأنه تم *إلغاء* حجز فيلا {$booking->villa->name} "
                . "بتاريخ {$booking->check_in->format('Y-m-d')}.\n\n"
                . "للاستفسار تواصلوا معنا. شكراً 🏡"
            );
        }
    }

    // ── Message builders ────────────────────────────────────────────────────

    private function buildOwnerMessage(Booking $booking, string $action): string
    {
        $checkInTime = $booking->check_in_time ? " الساعة {$booking->check_in_time}" : '';
        $numGuests   = $booking->num_guests ?? 1;
        $numRooms    = $booking->villa->num_rooms ?? '—';

        return "🏡 *إشعار حجز - {$action}*\n\n"
            . "الفيلا: {$booking->villa->name} ({$numRooms} غرف)\n"
            . "النزيل: {$booking->guest->name}\n"
            . "عدد الضيوف: {$numGuests}\n"
            . "تسجيل الدخول: {$booking->check_in->format('Y-m-d')}{$checkInTime}\n"
            . "تسجيل الخروج: {$booking->check_out->format('Y-m-d')}\n"
            . "عدد الليالي: {$booking->nights}\n"
            . "الإجمالي: " . number_format($booking->total_amount, 3) . " OMR\n"
            . "الحالة: {$this->translateStatus($booking->status)}\n"
            . "بواسطة: {$booking->user->name}";
    }

    private function buildGuestConfirmationMessage(Booking $booking): string
    {
        $checkInTime = $booking->check_in_time
            ? "الساعة {$booking->check_in_time}"
            : 'من 10:00 صباحاً حتى 2:00 ظهراً';
        $numRooms = $booking->villa->num_rooms ?? '—';

        $usageRules = config('services.villa.usage_rules',
            "• يُمنع إدخال أشخاص من خارج الحجز.\n"
            . "• يُمنع التدخين داخل الفيلا.\n"
            . "• يُرجى المحافظة على نظافة المكان.\n"
            . "• وقت تسجيل الخروج حتى الساعة 12:00 ظهراً.\n"
            . "• أي أضرار تقع على عاتق المستأجر."
        );

        return "أهلاً وسهلاً {$booking->guest->name} 🌟\n\n"
            . "*تأكيد حجز فيلا {$booking->villa->name}*\n\n"
            . "📋 *تفاصيل الحجز:*\n"
            . "• الفيلا: {$booking->villa->name} ({$numRooms} غرف)\n"
            . "• تاريخ الدخول: {$booking->check_in->format('Y-m-d')}\n"
            . "• وقت الدخول: {$checkInTime}\n"
            . "• تاريخ الخروج: {$booking->check_out->format('Y-m-d')}\n"
            . "• عدد الليالي: {$booking->nights}\n"
            . "• الإجمالي: " . number_format($booking->total_amount, 3) . " OMR\n\n"
            . "📌 *قوانين الاستخدام:*\n"
            . $usageRules . "\n\n"
            . "نتمنى لكم إقامة طيبة! 🏡";
    }

    private function sendToOwnerAndManagement(Booking $booking, string $message): void
    {
        $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
        if ($ownerPhone) {
            $this->sendMessage($ownerPhone, $message);
        }

        $managementPhone = config('services.whatsapp_cloud.management_number', '');
        if ($managementPhone && $managementPhone !== $ownerPhone) {
            $this->sendMessage($managementPhone, $message);
        }
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'pending'   => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'cancelled' => 'ملغي',
            'completed' => 'مكتمل',
            default     => $status,
        };
    }
}
