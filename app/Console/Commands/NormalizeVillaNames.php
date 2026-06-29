<?php

namespace App\Console\Commands;

use App\Models\Villa;
use Illuminate\Console\Command;

class NormalizeVillaNames extends Command
{
    protected $signature   = 'villas:normalize-names';
    protected $description = 'Strip the /xx/SUFFIX part from villa names, keeping only the leading number (e.g. 102/01/RHRS → 102)';

    public function handle(): int
    {
        $villas = Villa::all(['id', 'name']);

        $updated = 0;

        foreach ($villas as $villa) {
            $normalized = explode('/', $villa->name)[0];

            if ($normalized !== $villa->name) {
                $original = $villa->name;
                $villa->update(['name' => $normalized]);
                $this->line("  {$original} → {$normalized}");
                $updated++;
            }
        }

        $this->info("Done. {$updated} villa(s) updated.");

        return self::SUCCESS;
    }
}
