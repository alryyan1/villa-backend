<?php

namespace App\Console\Commands;

use App\Models\Owner;
use Illuminate\Console\Command;

class ClearOwnerContacts extends Command
{
    protected $signature = 'owners:clear-contacts
                            {--id= : Only clear the owner with this ID}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove phone and WhatsApp numbers from owners (all owners, or one via --id)';

    public function handle(): int
    {
        $query = Owner::query()
            ->where(function ($q) {
                $q->whereNotNull('phone')->orWhereNotNull('whatsapp_number');
            });

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $owners = $query->get(['id', 'name', 'phone', 'whatsapp_number']);

        if ($owners->isEmpty()) {
            $this->info('No owners with a phone or WhatsApp number found.');
            return self::SUCCESS;
        }

        $this->warn("Phone and WhatsApp numbers will be cleared for {$owners->count()} owner(s):");
        foreach ($owners as $owner) {
            $this->line("  • #{$owner->id} {$owner->name} (phone: {$owner->phone}, whatsapp: {$owner->whatsapp_number})");
        }
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Are you sure you want to clear these contacts? This cannot be undone.', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        Owner::whereIn('id', $owners->pluck('id'))->update([
            'phone'           => null,
            'whatsapp_number' => null,
        ]);

        $this->info("Done. {$owners->count()} owner(s) updated.");

        return self::SUCCESS;
    }
}
