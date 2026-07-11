<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
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

        $credentialsPath = config('services.firebase.credentials_path');
        if ($credentialsPath) {
            $isAbsolute = str_starts_with($credentialsPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath);
            $resolvedPath = $isAbsolute ? $credentialsPath : base_path($credentialsPath);

            if (file_exists($resolvedPath)) {
                $this->factory = (new Factory())->withServiceAccount($resolvedPath);
            }
        }
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

        $html = view('pdf.booking-confirmation', [
            'booking'         => $booking,
            'remaining'       => (float) $booking->total_amount - (float) $booking->paid_amount,
            'omr'             => $omr,
            'methodLabels'    => $methodLabels,
            'generatedAt'     => now()->format('d M Y, H:i'),
            'receptionPhone1' => Setting::get('reception_phone_1'),
            'receptionPhone2' => Setting::get('reception_phone_2'),
            'stampImagePath'  => ($stampImagePath && file_exists($stampImagePath)) ? $stampImagePath : null,
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
}
