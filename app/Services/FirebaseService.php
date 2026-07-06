<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

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
    public function uploadBookingPdf(int $bookingId, string $pdfBytes, ?string $guestPhone, ?string $ownerPhone): ?string
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
        if (!$this->isConfigured()) {
            Log::info("FirebaseService: not configured — skipping confirmation PDF for booking {$booking->id}.");
            return;
        }

        $booking->loadMissing(['villa.owner', 'guest', 'payments.user']);

        $methodLabels = ['cash' => 'Cash', 'card' => 'Card (Visa/MC)', 'bank_transfer' => 'Bank Transfer'];
        $omr = fn ($v) => 'OMR ' . number_format((float) $v, 3);

        $html = view('pdf.booking-confirmation', [
            'booking'      => $booking,
            'remaining'    => (float) $booking->total_amount - (float) $booking->paid_amount,
            'omr'          => $omr,
            'methodLabels' => $methodLabels,
            'generatedAt'  => now()->format('d M Y, H:i'),
        ])->render();

        $pdfBytes = Pdf::loadHTML($html)->setPaper('a4')->output();

        $ownerPhone = $booking->villa->owner->whatsapp_number ?? $booking->villa->owner->phone ?? null;

        $this->uploadBookingPdf($booking->id, $pdfBytes, $booking->guest->phone ?? null, $ownerPhone);
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D/', '', $phone);
        return $digits ?: null;
    }
}
