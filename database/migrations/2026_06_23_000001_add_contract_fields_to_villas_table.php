<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->date('contract_start_date')->nullable()->after('is_managed');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->decimal('contract_monthly_value', 10, 2)->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->dropColumn(['contract_start_date', 'contract_end_date', 'contract_monthly_value']);
        });
    }
};
