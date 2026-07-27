<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('original_nights')->nullable()->after('nights');
        });

        // Baseline existing bookings to their current nights, so extend/shorten
        // badges only reflect date edits made from this point forward.
        DB::table('bookings')->whereNull('original_nights')->update(['original_nights' => DB::raw('nights')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('original_nights');
        });
    }
};
