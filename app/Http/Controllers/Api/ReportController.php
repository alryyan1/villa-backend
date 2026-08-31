<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function occupancy(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $villas = Villa::with(['bookings' => function ($q) use ($from, $to) {
            $q->whereNotIn('status', ['cancelled'])
              ->where(function ($q2) use ($from, $to) {
                  $q2->whereBetween('check_in', [$from, $to])
                     ->orWhereBetween('check_out', [$from, $to])
                     ->orWhere(fn($q3) => $q3->where('check_in', '<=', $from)->where('check_out', '>=', $to));
              });
        }])->get()->map(function ($villa) use ($from, $to) {
            $totalDays    = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $bookedNights = $villa->bookings->sum('nights');
            return [
                'id'             => $villa->id,
                'name'           => $villa->name,
                'total_days'     => $totalDays,
                'booked_nights'  => $bookedNights,
                'occupancy_rate' => $totalDays > 0 ? round(($bookedNights / $totalDays) * 100, 1) : 0,
                'bookings_count' => $villa->bookings->count(),
            ];
        });

        return response()->json(['from' => $from, 'to' => $to, 'data' => $villas]);
    }

    public function revenue(Request $request)
    {
        $from    = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to      = $request->input('to', Carbon::now()->format('Y-m-d'));
        $groupBy = $request->input('group_by', 'month');

        $format = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'year'  => '%Y',
            default => '%Y-%m',
        };

        // paid_amount no longer stored on bookings — derive from payments table
        $data = Booking::select(
                DB::raw("DATE_FORMAT(bookings.check_in, '{$format}') as period"),
                DB::raw('SUM(bookings.total_amount) as total_revenue'),
                DB::raw('COALESCE(SUM(p.paid), 0) as total_paid'),
                DB::raw('COUNT(DISTINCT bookings.id) as bookings_count'),
                DB::raw('SUM(bookings.nights) as total_nights')
            )
            ->leftJoin(
                DB::raw('(SELECT booking_id, SUM(amount) as paid FROM payments GROUP BY booking_id) as p'),
                'p.booking_id', '=', 'bookings.id'
            )
            ->whereNotIn('bookings.status', ['cancelled'])
            ->where('bookings.is_owner', false)
            ->whereBetween('bookings.check_in', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totalRevenue = Booking::whereNotIn('status', ['cancelled'])
            ->where('is_owner', false)
            ->whereBetween('check_in', [$from, $to])
            ->sum('total_amount');

        $totalPaid = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereNotIn('bookings.status', ['cancelled'])
            ->where('bookings.is_owner', false)
            ->whereBetween('bookings.check_in', [$from, $to])
            ->sum('payments.amount');

        $bookingsCount = Booking::whereNotIn('status', ['cancelled'])
            ->where('is_owner', false)
            ->whereBetween('check_in', [$from, $to])
            ->count();

        $totals = [
            'total_revenue'  => (float) $totalRevenue,
            'total_paid'     => (float) $totalPaid,
            'bookings_count' => $bookingsCount,
        ];

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data, 'totals' => $totals]);
    }

    public function villaPerformance(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $data = Villa::with('owner')
            ->withCount(['bookings as total_bookings' => fn($q) => $q->whereNotIn('status', ['cancelled'])->where('is_owner', false)->whereBetween('check_in', [$from, $to])])
            ->withCount(['bookings as cancelled_bookings' => fn($q) => $q->where('status', 'cancelled')->where('is_owner', false)->whereBetween('check_in', [$from, $to])])
            ->withSum(['bookings as total_revenue' => fn($q) => $q->whereNotIn('status', ['cancelled'])->where('is_owner', false)->whereBetween('check_in', [$from, $to])], 'total_amount')
            ->withSum(['bookings as total_nights' => fn($q) => $q->whereNotIn('status', ['cancelled'])->where('is_owner', false)->whereBetween('check_in', [$from, $to])], 'nights')
            ->selectSub(function ($query) use ($from, $to) {
                $query->selectRaw('COALESCE(SUM(payments.amount), 0)')
                    ->from('payments')
                    ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
                    ->whereColumn('bookings.villa_id', 'villas.id')
                    ->whereNotIn('bookings.status', ['cancelled'])
                    ->where('bookings.is_owner', false)
                    ->whereBetween('bookings.check_in', [$from, $to]);
            }, 'total_collected')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function userPerformance(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $data = User::withCount(['bookings as total_bookings' => fn($q) => $q->whereNotIn('status', ['cancelled'])->where('is_owner', false)->whereBetween('check_in', [$from, $to])])
            ->withCount(['bookings as cancelled_bookings' => fn($q) => $q->where('status', 'cancelled')->where('is_owner', false)->whereBetween('check_in', [$from, $to])])
            ->withSum(['bookings as total_revenue' => fn($q) => $q->whereNotIn('status', ['cancelled'])->where('is_owner', false)->whereBetween('check_in', [$from, $to])], 'total_amount')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($user, $index) {
                $user->rank = $index + 1;
                return $user;
            });

        return response()->json(['from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function paymentMethods(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $rows = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('guests',   'bookings.guest_id',   '=', 'guests.id')
            ->select(
                'payments.method',
                DB::raw('SUM(payments.amount) as total_collected'),
                DB::raw('COUNT(payments.id) as payments_count'),
                DB::raw('COUNT(DISTINCT payments.booking_id) as bookings_count')
            )
            ->whereBetween('payments.payment_date', [$from, $to])
            ->groupBy('payments.method')
            ->orderByDesc('total_collected')
            ->get();

        $total = $rows->sum('total_collected');

        $data = $rows->map(fn($r) => [
            'method'           => $r->method,
            'total_collected'  => (float) $r->total_collected,
            'payments_count'   => (int)   $r->payments_count,
            'bookings_count'   => (int)   $r->bookings_count,
            'percentage'       => $total > 0 ? round(($r->total_collected / $total) * 100, 1) : 0,
        ]);

        return response()->json([
            'from'  => $from,
            'to'    => $to,
            'data'  => $data,
            'total' => (float) $total,
        ]);
    }

    public function bookingsSummary(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $summary = Booking::whereBetween('created_at', [$from, $to])
            ->where('is_owner', false)
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json(['from' => $from, 'to' => $to, 'data' => $summary]);
    }

    private function userBookingsData(string $from, string $to, $userId)
    {
        $paidSub = DB::table('payments')
            ->select('booking_id', DB::raw('SUM(amount) as paid'))
            ->groupBy('booking_id');

        $bookingsQuery = Booking::with(['villa', 'guest', 'user'])
            ->leftJoinSub($paidSub, 'p', fn ($join) => $join->on('p.booking_id', '=', 'bookings.id'))
            ->select('bookings.*', DB::raw('COALESCE(p.paid, 0) as paid_amount'))
            ->where('bookings.is_owner', false)
            ->whereBetween('bookings.check_in', [$from, $to]);

        if ($userId) {
            $bookingsQuery->where('bookings.user_id', $userId);
        }

        // All rows here are non-owner bookings, so commission is 5% of the
        // amount actually paid so far (not the full booking total). The
        // booking's user earns half of that commission as their own cut.
        return $bookingsQuery->orderByDesc('bookings.check_in')->get()
            ->each(function ($b) {
                $commission = round((float) $b->paid_amount * 0.05, 3);
                $b->setAttribute('commission_amount', $commission);
                $b->setAttribute('user_commission_amount', round($commission / 2, 3));
            });
    }

    public function userBookings(Request $request)
    {
        $from   = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to     = $request->input('to', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $userId = $request->input('user_id');

        $bookings = $this->userBookingsData($from, $to, $userId);

        $users = User::withCount(['bookings as total_bookings' => fn ($q) => $q->where('is_owner', false)->whereBetween('check_in', [$from, $to])])
            ->withSum(['bookings as total_amount' => fn ($q) => $q->where('is_owner', false)->whereBetween('check_in', [$from, $to])], 'total_amount')
            ->get()
            ->filter(fn ($u) => $u->total_bookings > 0)
            ->map(function ($user) use ($from, $to) {
                $paid = DB::table('payments')
                    ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
                    ->where('bookings.user_id', $user->id)
                    ->where('bookings.is_owner', false)
                    ->whereBetween('bookings.check_in', [$from, $to])
                    ->sum('payments.amount');
                $commission = round((float) $paid * 0.05, 3);
                $user->total_paid            = (float) $paid;
                $user->total_commission       = $commission;
                $user->total_user_commission  = round($commission / 2, 3);
                return $user;
            })
            ->values();

        $totals = [
            'bookings_count'        => $bookings->count(),
            'total_amount'          => (float) $bookings->sum('total_amount'),
            'total_paid'            => (float) $bookings->sum('paid_amount'),
            'total_commission'      => (float) $bookings->sum('commission_amount'),
            'total_user_commission' => (float) $bookings->sum('user_commission_amount'),
        ];

        return response()->json(['from' => $from, 'to' => $to, 'users' => $users, 'data' => $bookings, 'totals' => $totals]);
    }

    public function userBookingsExport(Request $request)
    {
        $from   = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to     = $request->input('to', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $userId = $request->input('user_id');

        $bookings     = $this->userBookingsData($from, $to, $userId);
        $filteredUser = $userId ? User::find($userId) : null;

        // When a single user is selected, drop the redundant "User" column
        // (mirrors the on-screen table's behaviour).
        $headers = $filteredUser
            ? ['Booking ID', 'Villa', 'Guest', 'Check-in', 'Check-out', 'Total', 'Paid', 'Commission (5% of paid)', 'User Commission (2.5%)']
            : ['Booking ID', 'User', 'Villa', 'Guest', 'Check-in', 'Check-out', 'Total', 'Paid', 'Commission (5% of paid)', 'User Commission (2.5%)'];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('User Bookings');
        $sheet->getSheetView()->setZoomScale(100);

        // --- Report header: company name, report title, period, filters ---
        $sheet->setCellValue('A1', 'Al Seef Villas');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'User Bookings Report');
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->setColor(new Color('FF595959'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Period: ' . Carbon::parse($from)->format('d M Y') . ' - ' . Carbon::parse($to)->format('d M Y'));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A4', 'User: ' . ($filteredUser->name ?? 'All Users'));
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A5', 'Generated: ' . Carbon::now()->format('d M Y H:i'));
        $sheet->mergeCells("A5:{$lastCol}5");
        $sheet->getStyle('A5')->getFont()->setSize(9)->setColor(new Color('FF8C8C8C'));
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- Table header ---
        $headerRow = 7;
        $sheet->fromArray($headers, null, "A{$headerRow}", true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1677FF');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- Data rows ---
        $row = $headerRow + 1;
        foreach ($bookings as $b) {
            $values = $filteredUser
                ? [$b->id, $b->villa->name ?? '—', $b->guest->name ?? '—']
                : [$b->id, $b->user->name ?? '—', $b->villa->name ?? '—', $b->guest->name ?? '—'];
            $values = array_merge($values, [
                Carbon::parse($b->check_in)->format('d M Y'),
                Carbon::parse($b->check_out)->format('d M Y'),
                (float) $b->total_amount,
                (float) $b->paid_amount,
                (float) $b->commission_amount,
                (float) $b->user_commission_amount,
            ]);
            $sheet->fromArray($values, null, "A{$row}", true);
            $row++;
        }
        $lastDataRow = $row - 1;

        $moneyCols = $filteredUser ? ['F', 'G', 'H', 'I'] : ['G', 'H', 'I', 'J'];
        foreach ($moneyCols as $col) {
            $sheet->getStyle("{$col}" . ($headerRow + 1) . ":{$col}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.000');
        }

        // --- Totals row ---
        $totalsRow = $row;
        $totalLabelCols = $filteredUser ? 5 : 6;
        $sheet->setCellValue('A' . $totalsRow, 'Total');
        $sheet->mergeCells('A' . $totalsRow . ':' . Coordinate::stringFromColumnIndex($totalLabelCols) . $totalsRow);
        $totalValues = [
            (float) $bookings->sum('total_amount'),
            (float) $bookings->sum('paid_amount'),
            (float) $bookings->sum('commission_amount'),
            (float) $bookings->sum('user_commission_amount'),
        ];
        $col = $totalLabelCols + 1;
        foreach ($totalValues as $value) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$colLetter}{$totalsRow}", $value);
            $sheet->getStyle("{$colLetter}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $col++;
        }
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F5FF');

        // --- Borders around the whole table ---
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$totalsRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFD9D9D9'));

        $sheet->freezePane('A' . ($headerRow + 1));

        foreach (range('A', $lastCol) as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $filename = 'user-bookings-' . $from . '-to-' . $to . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function ownerBookings(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to', Carbon::now()->format('Y-m-d'));

        $bookings = Booking::with(['villa.owner', 'guest', 'user'])
            ->where('is_owner', true)
            ->whereBetween('check_in', [$from, $to])
            ->orderByDesc('check_in')
            ->get();

        $totals = [
            'bookings_count' => $bookings->count(),
            'total_nights'   => (int) $bookings->sum('nights'),
        ];

        return response()->json(['from' => $from, 'to' => $to, 'data' => $bookings, 'totals' => $totals]);
    }
}
