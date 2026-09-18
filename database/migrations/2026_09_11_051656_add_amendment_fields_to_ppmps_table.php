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
            $table->string('amendment_status', 30)->nullable()->index()->after('status');
            $table->enum('amendment_type', ['SUPPLEMENTAL', 'AMENDMENT'])->nullable()->after('amendment_status');
            $table->text('amendment_reason')->nullable()->after('amendment_type');
            $table->timestamp('amendment_requested_at')->nullable()->after('amendment_reason');
            $table->timestamp('amendment_approved_at')->nullable()->after('amendment_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropColumn([
                'amendment_status',
                'amendment_type',
                'amendment_reason',
                'amendment_requested_at',
                'amendment_approved_at',
            ]);
        });
    }
};
