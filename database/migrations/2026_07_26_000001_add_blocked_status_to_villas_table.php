<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE villas MODIFY status ENUM('available', 'occupied', 'maintenance', 'blocked') DEFAULT 'available'");
    }

    public function down(): void
    {
        DB::statement("UPDATE villas SET status = 'maintenance' WHERE status = 'blocked'");
        DB::statement("ALTER TABLE villas MODIFY status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available'");
    }
};
