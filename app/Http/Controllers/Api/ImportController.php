<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Villa;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function importOwners(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            // Skip header row
            if ($index === 0) continue;

            $villaNo   = trim((string) ($row[0] ?? ''));
            $ownerName = trim((string) ($row[1] ?? ''));
            $phone     = trim((string) ($row[2] ?? ''));
            $contract  = trim((string) ($row[3] ?? ''));

            if (!$ownerName && !$villaNo) continue;

            try {
                // Find or create owner by name
                $owner = Owner::firstOrCreate(
                    ['name' => $ownerName],
                    ['phone' => $phone ?: null]
                );

                // Update phone if owner existed and has no phone
                if (!$owner->phone && $phone) {
                    $owner->update(['phone' => $phone]);
                }

                // Create villa if villa number provided
                if ($villaNo) {
                    $categoryMap = [
                        'monthly' => 'monthly',
                        'weekly'  => 'weekly',
                        'daily'   => 'daily',
                    ];
                    $category = null;
                    foreach ($categoryMap as $keyword => $value) {
                        if (stripos($contract, $keyword) !== false) {
                            $category = $value;
                            break;
                        }
                    }

                    Villa::firstOrCreate(
                        ['name' => $villaNo, 'owner_id' => $owner->id],
                        [
                            'category' => $category,
                            'status'   => 'available',
                        ]
                    );
                }

                $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Import complete. {$created} records processed, {$skipped} skipped.",
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }
}
