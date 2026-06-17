<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing 'transfer' values before changing the enum
        DB::table('payments')->where('method', 'transfer')->update(['method' => 'bank_transfer']);

        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        DB::table('payments')->where('method', 'bank_transfer')->update(['method' => 'transfer']);

        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'transfer', 'card', 'other') NOT NULL DEFAULT 'cash'");
    }
};
