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
            $table->string('requested_amendment_type')->nullable()->after('amendment_status');
            $table->text('requested_amendment_reason')->nullable()->after('requested_amendment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropColumn(['requested_amendment_type', 'requested_amendment_reason']);
        });
    }
};
