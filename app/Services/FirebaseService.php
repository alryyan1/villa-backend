<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use TCPDF;
use TCPDF_FONTS;

class FirebaseService
{
    private ?Factory $factory = null;
    private ?string $bucketName;

    public function __construct()
    {
        $this->bucketName = config('services.firebase.storage_bucket') ?: null;

        $resolvedPath = self::resolveCredentialsPath();
        if ($resolvedPath) {
            $this->factory = (new Factory())->withServiceAccount($resolvedPath);
        }
    }

    private static function resolveCredentialsPath(): ?string
    {
        $credentialsPath = config('services.firebase.credentials_path');
        if (!$credentialsPath) {
            return null;
        }

        $isAbsolute = str_starts_with($credentialsPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath);
        $resolvedPath = $isAbsolute ? $credentialsPath : base_path($credentialsPath);

        return file_exists($resolvedPath) ? $resolvedPath : null;
    }

    public function isConfigured(): bool
    {
        return $this->factory !== null && $this->bucketName !== null;
    }

    /**
     * Uploads the given PDF bytes to Firebase Storage, tagging the object with the
     * guest's and owner's normalized phone numbers as custom metadata. The Firebase
     * Function reads this metadata directly (no Firestore, no Laravel round-trip)
     * to authorize a "Download Pdf" WhatsApp button tap. Returns the storage object
     * path, or null if Firebase isn't configured or the upload fails.
     */
    public function uploadBookingPdf(int $bookingId, string $pdfBytes, ?string $guestPhone, ?string $ownerPhone, ?string $userPhone = null): ?string
    {
        if (!$this->isConfigured()) {
            Log::info('FirebaseService: not configured — skipping PDF upload.');
            return null;
        }

        $path = $this->bookingPdfPath($bookingId);

        try {
            $bucket = $this->factory->createStorage()->getBucket($this->bucketName);
            $bucket->upload($pdfBytes, [
                'name'     => $path,
                'metadata' => [
                    'contentType' => 'application/pdf',
                    'metadata'    => [
                        'guestPhone' => $this->normalizePhone($guestPhone) ?? '',
                        'ownerPhone' => $this->normalizePhone($ownerPhone) ?? '',
                        'userPhone'  => $this->normalizePhone($userPhone) ?? '',
                    ],
                ],
            ]);

            return $path;
        } catch (\Throwable $e) {
            Log::error("FirebaseService: PDF upload failed for booking {$bookingId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * The deterministic Storage path for a booking's confirmation PDF — no separate
     * lookup/index needed, the booking id alone tells us exactly where it lives.
     */
    public function bookingPdfPath(int $bookingId): string
    {
        return "booking-confirmations/{$bookingId}.pdf";
    }

    /**
     * Renders the booking confirmation as a PDF and uploads it to Firebase Storage.
     * No-ops silently if Firebase isn't configured.
     */
    public function generateAndStoreBookingConfirmation(Booking $booking): void
    {
        if (Setting::get('firebase_upload_enabled', '1') !== '1') {
            Log::info("FirebaseService: disabled via settings — skipping confirmation PDF for booking {$booking->id}.");
            return;
        }

        if (!$this->isConfigured()) {
            Log::info("FirebaseService: not configured — skipping confirmation PDF for booking {$booking->id}.");
            return;
        }

        $booking->loadMissing(['villa.owner', 'guest', 'user', 'payments.user']);

        $methodLabels = ['cash' => 'Cash', 'card' => 'Card (Visa/MC)', 'bank_transfer' => 'Bank Transfer'];
        $omr = fn ($v) => 'OMR ' . number_format((float) $v, 3);

        $stampImage = Setting::get('stamp_image');
        $stampImagePath = $stampImage ? storage_path('app/public/' . $stampImage) : null;

        $managementLogo = Setting::get('management_logo');
        $managementLogoPath = $managementLogo ? storage_path('app/public/' . $managementLogo) : null;

        $html = view('pdf.booking-confirmation', [
            'booking'             => $booking,
            'remaining'           => (float) $booking->total_amount - (float) $booking->paid_amount,
            'omr'                 => $omr,
            'methodLabels'        => $methodLabels,
            'generatedAt'         => now()->format('d M Y, H:i'),
            'receptionPhone1'     => Setting::get('reception_phone_1'),
            'receptionPhone2'     => Setting::get('reception_phone_2'),
            'stampImagePath'      => ($stampImagePath && file_exists($stampImagePath)) ? $stampImagePath : null,
            'managementLogoPath'  => ($managementLogoPath && file_exists($managementLogoPath)) ? $managementLogoPath : null,
        ])->render();

        $pdfBytes = $this->renderPdf($html);

        $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone ?? null;

        $this->uploadBookingPdf($booking->id, $pdfBytes, $booking->guest->phone ?? null, $ownerPhone, $booking->user->phone ?? null);
    }

    /**
     * Renders HTML to PDF bytes via TCPDF (rather than dompdf) because TCPDF has
     * native, reliable Arabic shaping/RTL support — needed for the terms &amp;
     * conditions section, which dompdf renders with mojibake unless heavily
     * patched. The Amiri TTF (SIL OFL licensed) is registered once per request
     * for the Arabic glyphs; the rest of the document uses TCPDF's built-in
     * Helvetica core font.
     */
    private function renderPdf(string $html): string
    {
        // Registers the font under the key "amiri", referenced via font-family:amiri in the view.
        TCPDF_FONTS::addTTFfont(public_path('fonts/Amiri-Regular.ttf'), 'TrueTypeUnicode', '', 96);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Al Seef');
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 8);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // writeHTML's dir="rtl" attribute alone doesn't reliably drive TCPDF's bidi/shaping
        // engine — the Arabic terms block must be written with setRTL(true) active, while the
        // English sections around it stay LTR. Split on the markers the view wraps it in.
        [$before, $rest] = explode('<!--RTL_START-->', $html, 2);
        [$rtlBlock, $after] = explode('<!--RTL_END-->', $rest, 2);

        $pdf->setRTL(false);
        $pdf->writeHTML($before, true, false, true, false, '');
        $pdf->setRTL(true);
        $pdf->writeHTML($rtlBlock, true, false, true, false, '');
        $pdf->setRTL(false);
        $pdf->writeHTML($after, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D/', '', $phone);
        return $digits ?: null;
    }

    /**
     * Returns a cached Google OAuth2 access token for the Firestore REST API,
     * refreshing shortly before Google's 1-hour expiry.
     */
    public static function getAccessToken(): ?string
    {
        return Cache::remember('firebase_access_token', 55 * 60, fn () => self::fetchNewAccessToken());
    }

    private static function fetchNewAccessToken(): ?string
    {
        $credentialsPath = self::resolveCredentialsPath();
        if (!$credentialsPath) {
            Log::error('FirebaseService: service account credentials file not found.');
            return null;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        if (!$credentials || !isset($credentials['private_key'], $credentials['client_email'])) {
            Log::error('FirebaseService: invalid service account credentials JSON.');
            return null;
        }

        try {
            $now    = time();
            $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim  = self::base64UrlEncode(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/firebase',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signingInput = "{$header}.{$claim}";
            $privateKey   = openssl_pkey_get_private($credentials['private_key']);
            if (!$privateKey) {
                Log::error('FirebaseService: failed to parse private key from service account.');
                return null;
            }

            openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = "{$signingInput}." . self::base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->successful() && ($token = $response->json('access_token'))) {
                return $token;
            }

            Log::error('FirebaseService: failed to obtain access token.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('FirebaseService: exception while fetching access token: ' . $e->getMessage());
            return null;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Persists an outbound (bot-sent) WhatsApp message to Firestore at
     * whatsapp_config/{slug}/chats/{waId}/messages/{wamid}, matching the schema
     * already used for this business's chat history (is_bot, message_body,
     * message_id, status, timestamp, type). No-ops silently if Firebase isn't
     * configured — chat logging must never block an actual WhatsApp send.
     */
    public function logOutgoingWhatsAppMessage(string $waId, string $wamid, string $body): void
    {
        $projectId = config('services.firebase.project_id');
        $slug      = config('services.firebase.whatsapp_chat_slug');
        $waId      = $this->normalizePhone($waId);

        if (!$projectId || !$slug || !$waId) {
            return;
        }

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return;
        }

        try {
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents"
                . "/whatsapp_config/{$slug}/chats/{$waId}/messages/{$wamid}";

            $response = Http::withToken($accessToken)->patch($url, [
                'fields' => [
                    'is_bot'       => ['booleanValue' => true],
                    'message_body' => ['stringValue' => $body],
                    'message_id'   => ['stringValue' => $wamid],
                    'status'       => ['stringValue' => 'sent'],
                    'timestamp'    => ['timestampValue' => now()->toISOString()],
                    'type'         => ['stringValue' => 'sent'],
                ],
            ]);

            if (!$response->successful()) {
                Log::warning('FirebaseService: failed to log outgoing WhatsApp message.', [
                    'wamid' => $wamid, 'status' => $response->status(), 'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FirebaseService: exception while logging outgoing WhatsApp message: ' . $e->getMessage());
        }
    }
}
