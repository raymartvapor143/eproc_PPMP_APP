<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropUnique('ppmps_tracking_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only re-add unique constraint if there are no duplicates
        $hasDuplicates = \Illuminate\Support\Facades\DB::table('ppmps')
            ->select('tracking_number')
            ->groupBy('tracking_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (!$hasDuplicates) {
            Schema::table('ppmps', function (Blueprint $table) {
                $table->unique('tracking_number');
            });
        }
    }
};
