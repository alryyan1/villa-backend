<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Villa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppBotService
{
    private const VALID_ACTIONS = [
        'checkin_today', 'checkout_today', 'checkin_tomorrow', 'checkout_tomorrow',
        'revenue_today', 'pending_bookings', 'expiring_contracts',
        'occupancy_today', 'turnover_today', 'full_daily_report',
    ];

    public function __construct(private WhatsAppCloudApiService $api) {}

    public function handle(string $from, string $actionId): void
    {
        $normalizedFrom = preg_replace('/\D/', '', $from);

        if (!$this->isAllowed($normalizedFrom)) {
            Log::warning("WhatsAppBotService: rejected unauthorized sender {$from}");
            return;
        }

        if (!in_array($actionId, self::VALID_ACTIONS, true)) {
            Log::info("WhatsAppBotService: unknown action_id {$actionId} from {$from}");
            $this->api->sendTextMessage($normalizedFrom, "Sorry, I didn't recognize that option. Reply MENU to see available reports.");
            return;
        }

        if ($actionId === 'full_daily_report') {
            $this->sendPdf($normalizedFrom, 'Full Daily Report — ' . Carbon::today()->format('d M Y'), [
                $this->checkinToday(),
                $this->checkoutToday(),
                $this->checkinTomorrow(),
                $this->checkoutTomorrow(),
                $this->revenueToday(),
                $this->pendingBookings(),
                $this->expiringContracts(),
                $this->occupancyToday(),
                $this->turnoverToday(),
            ]);
            return;
        }

        $section = match ($actionId) {
            'checkin_today'      => $this->checkinToday(),
            'checkout_today'     => $this->checkoutToday(),
            'checkin_tomorrow'   => $this->checkinTomorrow(),
            'checkout_tomorrow'  => $this->checkoutTomorrow(),
            'revenue_today'      => $this->revenueToday(),
            'pending_bookings'   => $this->pendingBookings(),
            'expiring_contracts' => $this->expiringContracts(),
            'occupancy_today'    => $this->occupancyToday(),
            'turnover_today'     => $this->turnoverToday(),
        };

        $this->sendPdf($normalizedFrom, $section['title'], [$section]);
    }

    private function isAllowed(string $normalizedFrom): bool
    {
        foreach (config('services.whatsapp_cloud.admin_numbers', []) as $number) {
            if (preg_replace('/\D/', '', $number) === $normalizedFrom) {
                return true;
            }
        }
        return false;
    }

    // ── Report builders ─────────────────────────────────────────────────────

    private function bookingsByDate(string $column, Carbon $date, string $title): array
    {
        $bookings = Booking::with(['villa:id,name', 'guest:id,name'])
            ->whereDate($column, $date)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy($column)
            ->get();

        $rows = $bookings->map(fn (Booking $b) => [
            $b->guest->name ?? '—',
            $b->villa->name ?? '—',
            $column === 'check_in' ? ($b->check_in_time ?? '—') : '—',
            ucfirst($b->status),
        ])->all();

        return [
            'title'   => $title,
            'note'    => null,
            'columns' => ['Guest', 'Villa', $column === 'check_in' ? 'Time' : 'Nights', 'Status'],
            'rows'    => $rows,
        ];
    }

    private function checkinToday(): array
    {
        return $this->bookingsByDate('check_in', Carbon::today(), "Today's Check-ins (" . Carbon::today()->format('d M Y') . ')');
    }

    private function checkoutToday(): array
    {
        return $this->bookingsByDate('check_out', Carbon::today(), "Today's Check-outs (" . Carbon::today()->format('d M Y') . ')');
    }

    private function checkinTomorrow(): array
    {
        return $this->bookingsByDate('check_in', Carbon::tomorrow(), "Tomorrow's Check-ins (" . Carbon::tomorrow()->format('d M Y') . ')');
    }

    private function checkoutTomorrow(): array
    {
        return $this->bookingsByDate('check_out', Carbon::tomorrow(), "Tomorrow's Check-outs (" . Carbon::tomorrow()->format('d M Y') . ')');
    }

    private function revenueToday(): array
    {
        $today = Carbon::today();

        $payments = Payment::with(['booking.villa:id,name', 'booking.guest:id,name'])
            ->whereDate('payment_date', $today)
            ->orderBy('created_at')
            ->get();

        $rows = $payments->map(fn (Payment $p) => [
            $p->booking->guest->name ?? '—',
            $p->booking->villa->name ?? '—',
            ucfirst($p->method),
            number_format((float) $p->amount, 3) . ' OMR',
        ])->all();

        return [
            'title'   => "Today's Revenue (" . $today->format('d M Y') . ')',
            'note'    => 'Total collected: ' . number_format((float) $payments->sum('amount'), 3) . ' OMR (' . $payments->count() . ' payments)',
            'columns' => ['Guest', 'Villa', 'Method', 'Amount'],
            'rows'    => $rows,
        ];
    }

    private function pendingBookings(): array
    {
        $bookings = Booking::with(['villa:id,name', 'guest:id,name'])
            ->where('status', 'pending')
            ->where('check_in', '>=', Carbon::today())
            ->orderBy('check_in')
            ->get();

        $rows = $bookings->map(fn (Booking $b) => [
            $b->guest->name ?? '—',
            $b->villa->name ?? '—',
            $b->check_in->format('d M Y'),
            $b->check_out->format('d M Y'),
        ])->all();

        return [
            'title'   => 'Pending Bookings',
            'note'    => null,
            'columns' => ['Guest', 'Villa', 'Check-in', 'Check-out'],
            'rows'    => $rows,
        ];
    }

    private function expiringContracts(): array
    {
        $today = Carbon::today();

        $villas = Villa::whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '>=', $today)
            ->whereDate('contract_end_date', '<=', $today->copy()->addDays(30))
            ->orderBy('contract_end_date')
            ->get();

        $rows = $villas->map(fn (Villa $v) => [
            $v->name,
            $v->contract_end_date->format('d M Y'),
            $v->contract_end_date->diffInDays($today) . ' days left',
        ])->all();

        return [
            'title'   => 'Contracts Expiring in 30 Days',
            'note'    => null,
            'columns' => ['Villa', 'Contract End', 'Remaining'],
            'rows'    => $rows,
        ];
    }

    private function occupancyToday(): array
    {
        $today = Carbon::today()->format('Y-m-d');

        $villas = Villa::with(['bookings' => function ($q) use ($today) {
            $q->whereNotIn('status', ['cancelled'])
              ->where('check_in', '<=', $today)
              ->where('check_out', '>=', $today)
              ->with('guest:id,name');
        }])->get();

        $occupied = $villas->filter(fn (Villa $v) => $v->bookings->isNotEmpty())->count();
        $total    = $villas->count();
        $rate     = $total > 0 ? round(($occupied / $total) * 100, 1) : 0;

        $rows = $villas->map(fn (Villa $v) => [
            $v->name,
            $v->bookings->isNotEmpty() ? 'Occupied' : 'Vacant',
            $v->bookings->first()->guest->name ?? '—',
        ])->all();

        return [
            'title'   => 'Occupancy — ' . Carbon::today()->format('d M Y'),
            'note'    => "Occupied: {$occupied}/{$total} villas ({$rate}%)",
            'columns' => ['Villa', 'Status', 'Guest'],
            'rows'    => $rows,
        ];
    }

    private function turnoverToday(): array
    {
        $today = Carbon::today()->format('Y-m-d');

        $checkouts = Booking::with(['villa:id,name', 'guest:id,name'])
            ->whereDate('check_out', $today)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $sameDayCheckins = Booking::whereDate('check_in', $today)
            ->where('status', 'confirmed')
            ->pluck('villa_id')
            ->flip();

        $rows = $checkouts->map(fn (Booking $b) => [
            $b->villa->name ?? '—',
            $b->guest->name ?? '—',
            isset($sameDayCheckins[$b->villa_id]) ? 'Same-day turnover ⚠️' : 'Standard cleaning',
        ])->all();

        return [
            'title'   => 'Turnover / Cleaning — ' . Carbon::today()->format('d M Y'),
            'note'    => count($rows) . ' villa(s) checking out today',
            'columns' => ['Villa', 'Last Guest', 'Turnover'],
            'rows'    => $rows,
        ];
    }

    // ── PDF rendering + delivery ─────────────────────────────────────────────

    private function sendPdf(string $to, string $pdfTitle, array $sections): void
    {
        $html = view('pdf.whatsapp-report', [
            'pdfTitle'    => $pdfTitle,
            'generatedAt' => now()->format('d M Y, H:i'),
            'sections'    => $sections,
        ])->render();

        $pdfBytes = Pdf::loadHTML($html)->setPaper('a4')->output();
        $filename = Str::slug($pdfTitle) . '-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.pdf';

        $upload = $this->api->uploadMedia($pdfBytes, $filename);

        if (!($upload['success'] ?? false)) {
            Log::error("WhatsAppBotService: failed to upload PDF for {$to}: " . ($upload['error'] ?? 'unknown'));
            return;
        }

        $result = $this->api->sendDocumentById($to, $upload['media_id'], $filename, $pdfTitle);

        if (!($result['success'] ?? false)) {
            Log::error("WhatsAppBotService: failed to send PDF to {$to}: " . ($result['error'] ?? 'unknown'));
        }
    }
}
