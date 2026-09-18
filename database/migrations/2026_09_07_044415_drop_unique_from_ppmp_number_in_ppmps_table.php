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
            $table->dropUnique('ppmps_ppmp_number_unique');
            // Ensure tracking_number is unique
            $table->unique('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('ppmps', function (Blueprint $table) {
                $table->dropUnique(['tracking_number']);
            });
        } catch (\Throwable $e) {
            // Index may not exist if rollback sequence or previous migration didn't recreate it
        }

        try {
            Schema::table('ppmps', function (Blueprint $table) {
                $table->unique('ppmp_number');
            });
        } catch (\Throwable $e) {
            // ppmp_number unique index already exists or has duplicate values
        }
    }
};
