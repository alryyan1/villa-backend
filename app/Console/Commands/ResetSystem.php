<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ResetSystem extends Command
{
    protected $signature = 'system:reset
                            {--full : Also wipe owners, villas and re-seed admin users}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset system data. Clears bookings, payments, guests and activity logs. Use --full to also wipe owners and villas.';

    public function handle(): int
    {
        $full = $this->option('full');

        $tables = $full
            ? ['activity_logs', 'payments', 'bookings', 'guests', 'villas', 'owners', 'personal_access_tokens']
            : ['activity_logs', 'payments', 'bookings', 'guests', 'personal_access_tokens'];

        $this->warn('The following tables will be truncated:');
        foreach ($tables as $t) {
            $this->line("  • {$t}");
        }
        if ($full) {
            $this->line('  • users  (will be re-seeded with defaults)');
        }
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset? This cannot be undone.', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->line("  ✓ Truncated <fg=yellow>{$table}</>");
        }

        if ($full) {
            DB::table('users')->truncate();
            $this->line('  ✓ Truncated <fg=yellow>users</>');

            User::create([
                'name'      => 'System Admin',
                'email'     => 'admin@villa.com',
                'password'  => Hash::make('password123'),
                'role'      => 'admin',
                'is_active' => true,
            ]);
            User::create([
                'name'      => 'Manager',
                'email'     => 'manager@villa.com',
                'password'  => Hash::make('password123'),
                'role'      => 'manager',
                'is_active' => true,
            ]);
            User::create([
                'name'      => 'Staff User',
                'email'     => 'staff@villa.com',
                'password'  => Hash::make('password123'),
                'role'      => 'staff',
                'is_active' => true,
            ]);
            $this->line('  ✓ Default users re-seeded');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('System reset complete.');

        return self::SUCCESS;
    }
}
