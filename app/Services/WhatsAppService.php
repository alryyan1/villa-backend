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

    public function sendMessage(string $phone, string $message, string $countryCode = '968'): bool
    {
        if (!$this->isEnabled()) {
            Log::info("WhatsApp (disabled): to={$phone}, msg={$message}");
            return false;
        }

        if (!$this->api->isConfigured()) {
            Log::info("WhatsApp (not configured): to={$phone}, msg={$message}");
            return false;
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
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

        $results  = ['owner' => null, 'tenant' => null, 'user' => null];
        $noPhone = ['sent' => false, 'error' => 'No phone number'];

        // Owner template — bookings the owner made for themselves (Booking::is_owner)
        // use the same template with commission forced to 0.
        if (Setting::get('owner_notifications_enabled', '1') === '1') {
            $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
            if ($ownerPhone) {
                Log::info("WhatsApp: sending booking notification to owner: {$ownerPhone}");
                $results['owner'] = $this->sendOwnerBookingTemplate($booking, $ownerPhone);
            } else {
                $results['owner'] = $noPhone;
            }
        } else {
            $results['owner'] = ['sent' => false, 'error' => 'Owner notifications disabled'];
        }

        $tenantTemplateSender = $booking->status === 'pending'
            ? fn (Booking $b, string $phone, string $cc) => $this->sendTenantPendingBookingTemplate($b, $phone, $cc)
            : fn (Booking $b, string $phone, string $cc) => $this->sendTenantBookingTemplate($b, $phone, $cc);

        if (Setting::get('tenant_notifications_enabled', '1') !== '0') {
            if ($booking->guest->phone) {
                Log::info("WhatsApp: sending booking notification to tenant: {$booking->guest->phone}");
                $results['tenant'] = $tenantTemplateSender($booking, $booking->guest->phone, $booking->guest->country_code ?: '968');
            } else {
                $results['tenant'] = $noPhone;
            }
        } else {
            $results['tenant'] = ['sent' => false, 'error' => 'Tenant notifications disabled'];
        }

        // Staff member who created the booking — its own dedicated template, sent
        // regardless of the booking's confirmed/pending status.
        if ($booking->user->phone) {
            Log::info("WhatsApp: sending booking notification to logged-in user: {$booking->user->phone}");
            $results['user'] = $this->sendUserBookingTemplate($booking, $booking->user->phone);
        } else {
            $results['user'] = $noPhone;
        }

        return $results;
    }

    /**
     * Notifies owner, tenant, and the staff member about a non-extend edit
     * (villa/dates/guest/etc. changed, but not a nights increase — that's
     * notifyBookingExtended's job). Same shared edit_booking template for
     * all three recipients, mirroring notifyBookingCancelled's shape.
     */
    public function notifyBookingEdited(Booking $booking): array
    {
        $booking->load(['villa.owner', 'guest', 'user']);

        $results = ['owner' => null, 'tenant' => null, 'user' => null];
        $noPhone = ['sent' => false, 'error' => 'No phone number'];

        if (Setting::get('owner_notifications_enabled', '1') === '1') {
            $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
            if ($ownerPhone) {
                $results['owner'] = $this->sendEditBookingTemplate($booking, $ownerPhone);
            } else {
                $results['owner'] = $noPhone;
            }
        } else {
            $results['owner'] = ['sent' => false, 'error' => 'Owner notifications disabled'];
        }

        if (Setting::get('tenant_notifications_enabled', '1') !== '0') {
            if ($booking->guest->phone) {
                $results['tenant'] = $this->sendEditBookingTemplate($booking, $booking->guest->phone, $booking->guest->country_code ?: '968');
            } else {
                $results['tenant'] = $noPhone;
            }
        } else {
            $results['tenant'] = ['sent' => false, 'error' => 'Tenant notifications disabled'];
        }

        if ($booking->user->phone) {
            $results['user'] = $this->sendEditBookingTemplate($booking, $booking->user->phone);
        } else {
            $results['user'] = $noPhone;
        }

        return $results;
    }

    private function sendEditBookingTemplate(Booking $booking, string $phone, string $countryCode = '968'): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('edit_booking_template', 'edit_booking');
        $templateLang = Setting::get('edit_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: edit booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for edit notification: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $receptionPhone1 = Setting::get('reception_phone_1', '');
        $receptionPhone2 = Setting::get('reception_phone_2', '');

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

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "✏️ تعديل حجز رقم {$booking->id} — فيلا {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp edit booking template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    /**
     * Notifies tenant, owner, and the staff member on a booking extension
     * (check-out pushed later). Mirrors notifyBookingCreated's per-recipient
     * shape ({owner, tenant, user}) so the frontend can show the same
     * send-status modal it already uses for new bookings.
     */
    public function notifyBookingExtended(Booking $booking, int $oldNights, string $oldCheckOut): array
    {
        $booking->load(['villa.owner', 'guest', 'user']);
        $extraNights = $booking->nights - $oldNights;

        $results = ['owner' => null, 'tenant' => null, 'user' => null];
        $noPhone = ['sent' => false, 'error' => 'No phone number'];

        if (Setting::get('owner_notifications_enabled', '1') === '1') {
            $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
            if ($ownerPhone) {
                $results['owner'] = $this->sendOwnerExtendTemplate($booking, $extraNights, $oldCheckOut, $ownerPhone);
            } else {
                $results['owner'] = $noPhone;
            }
        } else {
            $results['owner'] = ['sent' => false, 'error' => 'Owner notifications disabled'];
        }

        if (Setting::get('tenant_notifications_enabled', '1') !== '0') {
            if ($booking->guest->phone) {
                $results['tenant'] = $this->sendTenantExtendTemplate(
                    $booking, $extraNights, $oldCheckOut, $booking->guest->phone, $booking->guest->country_code ?: '968'
                );
            } else {
                $results['tenant'] = $noPhone;
            }
        } else {
            $results['tenant'] = ['sent' => false, 'error' => 'Tenant notifications disabled'];
        }

        if ($booking->user->phone) {
            $results['user'] = $this->sendUserExtendTemplate($booking, $extraNights, $booking->user->phone);
        } else {
            $results['user'] = $noPhone;
        }

        return $results;
    }

    /**
     * Notifies owner, tenant, and the staff member that a booking was
     * cancelled/deleted, using the shared cancel_booking Meta template for
     * all three recipients. Mirrors notifyBookingCreated's per-recipient
     * shape ({owner, tenant, user}) so the frontend can show the same
     * send-status modal it already uses for new bookings.
     */
    public function notifyBookingCancelled(Booking $booking): array
    {
        $booking->load(['villa.owner', 'guest', 'user']);

        $results = ['owner' => null, 'tenant' => null, 'user' => null];
        $noPhone = ['sent' => false, 'error' => 'No phone number'];

        if (Setting::get('owner_notifications_enabled', '1') === '1') {
            $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone;
            if ($ownerPhone) {
                $results['owner'] = $this->sendCancelBookingTemplate($booking, $booking->villa->owner->name, $ownerPhone);
            } else {
                $results['owner'] = $noPhone;
            }
        } else {
            $results['owner'] = ['sent' => false, 'error' => 'Owner notifications disabled'];
        }

        if (Setting::get('tenant_notifications_enabled', '1') !== '0') {
            if ($booking->guest->phone) {
                $results['tenant'] = $this->sendCancelBookingTemplate(
                    $booking, $booking->guest->name, $booking->guest->phone, $booking->guest->country_code ?: '968'
                );
            } else {
                $results['tenant'] = $noPhone;
            }
        } else {
            $results['tenant'] = ['sent' => false, 'error' => 'Tenant notifications disabled'];
        }

        if ($booking->user->phone) {
            $results['user'] = $this->sendCancelBookingTemplate($booking, $booking->user->name, $booking->user->phone);
        } else {
            $results['user'] = $noPhone;
        }

        return $results;
    }

    /**
     * Shared cancel_booking template, sent as-is to owner, tenant, and staff —
     * only the recipient's own name ({{1}}) differs between the three sends.
     */
    private function sendCancelBookingTemplate(Booking $booking, string $recipientName, string $phone, string $countryCode = '968'): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('cancel_booking_template', 'cancel_booking');
        $templateLang = Setting::get('cancel_booking_template_lang', 'en');

        if (empty($templateName)) {
            Log::info('WhatsApp: cancel booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for cancel notification: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $recipientName],
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "❌ إلغاء حجز رقم {$booking->id} — فيلا {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp cancel booking template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    // ── Message builders ────────────────────────────────────────────────────

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

    /**
     * Notifies the villa owner about a new booking. When the booking is the
     * owner's own (Booking::is_owner), a distinct Meta template is used whose
     * text has a fixed "حجز عن طريق المالك" line and no commission line — the
     * commission is forced to 0 and net equals the total.
     */
    private function sendOwnerBookingTemplate(Booking $booking, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $isOwner = $booking->is_owner;

        $templateName = $isOwner
            ? Setting::get('owner_self_booking_template', '')
            : Setting::get('owner_booking_template', '');
        $templateLang = $isOwner
            ? Setting::get('owner_self_booking_template_lang', 'ar')
            : Setting::get('owner_booking_template_lang', 'ar');

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
        $commission = $isOwner ? 0.0 : $total * 0.05;
        $net        = $total - $commission;

        $bodyParams = [
            ['type' => 'text', 'text' => (string) $booking->id],
            ['type' => 'text', 'text' => $booking->villa->name],
            ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
            ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
            ['type' => 'text', 'text' => number_format($total, 3)],
        ];
        if (!$isOwner) {
            $bodyParams[] = ['type' => 'text', 'text' => number_format($commission, 3)];
        }
        $bodyParams[] = ['type' => 'text', 'text' => number_format($net, 3)];

        $components = [['type' => 'body', 'parameters' => $bodyParams]];

        // Only attach a button component if the currently-configured template actually
        // has a "Download Pdf" quick-reply button — the live default template does not,
        // and Meta rejects button params that don't match the approved template.
        $hasButtonSetting = $isOwner ? 'owner_self_booking_template_has_button' : 'owner_booking_template_has_button';
        if (Setting::get($hasButtonSetting, '0') === '1') {
            $components[] = [
                'type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0',
                'parameters' => [['type' => 'payload', 'payload' => "download_pdf:{$booking->id}"]],
            ];
        }

        $logBody = $isOwner
            ? "🏡 حجز عن طريق المالك رقم {$booking->id} — {$booking->villa->name}"
            : "🏡 إشعار حجز رقم {$booking->id} — {$booking->villa->name} (صافي " . number_format($net, 3) . " ر.ع)";
        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null, $logBody);

        if (!$result['success']) {
            Log::error('WhatsApp owner template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendTenantBookingTemplate(Booking $booking, string $phone, string $countryCode = '968'): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('guest_booking_template', '');
        $templateLang = Setting::get('guest_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: tenant booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for tenant: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $receptionPhone1 = Setting::get('reception_phone_1', '');
        $receptionPhone2 = Setting::get('reception_phone_2', '');

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

        // Only attach a button component if the currently-configured template actually
        // has a "Download Pdf" quick-reply button — the live default template does not,
        // and Meta rejects button params that don't match the approved template.
        if (Setting::get('guest_booking_template_has_button', '0') === '1') {
            $components[] = [
                'type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0',
                'parameters' => [['type' => 'payload', 'payload' => "download_pdf:{$booking->id}"]],
            ];
        }

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "✅ تأكيد حجز رقم {$booking->id} — فيلا {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp tenant template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendUserBookingTemplate(Booking $booking, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('user_booking_template', '');
        $templateLang = Setting::get('user_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: user booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, '968');
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for user: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $remaining = (float) $booking->total_amount - (float) $booking->paid_amount;

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => (string) $booking->id],
                ['type' => 'text', 'text' => $booking->guest->phone ?? ''],
                ['type' => 'text', 'text' => $booking->check_in->format('Y-m-d')],
                ['type' => 'text', 'text' => (string) $booking->nights],
                ['type' => 'text', 'text' => number_format($remaining, 3)],
            ],
        ]];

        // Only attach a button component if the currently-configured template actually
        // has a "Download Pdf" quick-reply button — the live default template does not,
        // and Meta rejects button params that don't match the approved template.
        if (Setting::get('user_booking_template_has_button', '0') === '1') {
            $components[] = [
                'type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0',
                'parameters' => [['type' => 'payload', 'payload' => "download_pdf:{$booking->id}"]],
            ];
        }

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "📋 إشعار حجز رقم {$booking->id} — {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp user template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendTenantExtendTemplate(Booking $booking, int $extraNights, string $oldCheckOut, string $phone, string $countryCode = '968'): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('guest_extend_booking_template', '');
        $templateLang = Setting::get('guest_extend_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: guest extend template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for tenant: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $receptionPhone1 = Setting::get('reception_phone_1', '');
        $receptionPhone2 = Setting::get('reception_phone_2', '');

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => (string) $booking->id],
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => $oldCheckOut],
                ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
                ['type' => 'text', 'text' => (string) $extraNights],
                ['type' => 'text', 'text' => number_format((float) $booking->total_amount, 3)],
                ['type' => 'text', 'text' => $receptionPhone1],
                ['type' => 'text', 'text' => $receptionPhone2],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "⏳ تمديد حجز رقم {$booking->id} — فيلا {$booking->villa->name} (+{$extraNights} ليالي)");

        if (!$result['success']) {
            Log::error('WhatsApp tenant extend template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendUserExtendTemplate(Booking $booking, int $extraNights, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('user_extend_booking_template', '');
        $templateLang = Setting::get('user_extend_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: user extend template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, '968');
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for user: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $remaining = (float) $booking->total_amount - (float) $booking->paid_amount;

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $booking->villa->name],
                ['type' => 'text', 'text' => (string) $booking->id],
                ['type' => 'text', 'text' => $booking->guest->phone ?? ''],
                ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
                ['type' => 'text', 'text' => (string) $extraNights],
                ['type' => 'text', 'text' => number_format($remaining, 3)],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "🔔 تمديد حجز رقم {$booking->id} — {$booking->villa->name} (+{$extraNights} ليالي)");

        if (!$result['success']) {
            Log::error('WhatsApp user extend template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    /**
     * Owner extend notification. When the booking is the owner's own
     * (Booking::is_owner), a distinct Meta template is used with no
     * commission line — same convention as sendOwnerBookingTemplate.
     */
    private function sendOwnerExtendTemplate(Booking $booking, int $extraNights, string $oldCheckOut, string $phone): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $isOwner = $booking->is_owner;

        $templateName = $isOwner
            ? Setting::get('owner_self_extend_booking_template', '')
            : Setting::get('owner_extend_booking_template', '');
        $templateLang = $isOwner
            ? Setting::get('owner_self_extend_booking_template_lang', 'ar')
            : Setting::get('owner_extend_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: owner extend template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for owner: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $total      = (float) $booking->total_amount;
        $commission = $isOwner ? 0.0 : $total * 0.05;
        $net        = $total - $commission;

        $bodyParams = [
            ['type' => 'text', 'text' => (string) $booking->id],
            ['type' => 'text', 'text' => $booking->villa->name],
            ['type' => 'text', 'text' => $oldCheckOut],
            ['type' => 'text', 'text' => $booking->check_out->format('Y-m-d')],
            ['type' => 'text', 'text' => (string) $extraNights],
            ['type' => 'text', 'text' => number_format($total, 3)],
        ];
        if (!$isOwner) {
            $bodyParams[] = ['type' => 'text', 'text' => number_format($commission, 3)];
        }
        $bodyParams[] = ['type' => 'text', 'text' => number_format($net, 3)];

        $components = [['type' => 'body', 'parameters' => $bodyParams]];

        $logBody = $isOwner
            ? "🏡 تمديد حجز عن طريق المالك رقم {$booking->id} — {$booking->villa->name} (+{$extraNights} ليالي)"
            : "🏡 تمديد حجز رقم {$booking->id} — {$booking->villa->name} (صافي جديد " . number_format($net, 3) . " ر.ع)";

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null, $logBody);

        if (!$result['success']) {
            Log::error('WhatsApp owner extend template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    private function sendTenantPendingBookingTemplate(Booking $booking, string $phone, string $countryCode = '968'): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('guest_pending_booking_template', '');
        $templateLang = Setting::get('guest_pending_booking_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: pending tenant booking template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $normalized = WhatsAppCloudApiService::formatPhoneNumber($phone, $countryCode);
        if (!$normalized) {
            Log::warning("WhatsApp: invalid phone for tenant: {$phone}");
            return ['sent' => false, 'error' => 'Invalid phone number'];
        }

        $receptionPhone1 = Setting::get('reception_phone_1', '');
        $receptionPhone2 = Setting::get('reception_phone_2', '');

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

        // Only attach a button component if the currently-configured template actually
        // has a "Download Pdf" quick-reply button — the live default template does not,
        // and Meta rejects button params that don't match the approved template.
        if (Setting::get('guest_pending_booking_template_has_button', '0') === '1') {
            $components[] = [
                'type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0',
                'parameters' => [['type' => 'payload', 'payload' => "download_pdf:{$booking->id}"]],
            ];
        }

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "⏳ حجز معلق رقم {$booking->id} — فيلا {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp pending tenant template failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

    public function sendCheckoutReminder(Booking $booking): array
    {
        if (!$this->isEnabled()) return ['sent' => false, 'error' => 'WhatsApp disabled'];

        $templateName = Setting::get('guest_checkout_reminder_template', '');
        $templateLang = Setting::get('guest_checkout_reminder_template_lang', 'ar');

        if (empty($templateName)) {
            Log::info('WhatsApp: checkout reminder template not configured — skipping.');
            return ['sent' => false, 'error' => 'Template not configured'];
        }

        $phone = $booking->guest->phone ?? null;
        $normalized = $phone ? WhatsAppCloudApiService::formatPhoneNumber($phone, $booking->guest->country_code ?: '968') : null;
        if (!$normalized) {
            Log::warning("WhatsApp: invalid or missing phone for guest on booking {$booking->id}");
            return ['sent' => false, 'error' => 'Invalid or missing phone number'];
        }

        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $booking->guest->name],
                ['type' => 'text', 'text' => $booking->villa->name],
            ],
        ]];

        $result = $this->api->sendTemplateMessage($normalized, $templateName, $templateLang, $components, null, null,
            "⏰ تذكير مغادرة — حجز رقم {$booking->id} — {$booking->villa->name}");

        if (!$result['success']) {
            Log::error('WhatsApp checkout reminder failed: ' . ($result['error'] ?? 'unknown'));
        }

        return ['sent' => $result['success'], 'error' => $result['error'] ?? null];
    }

}
