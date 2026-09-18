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
            $table->timestamp('head_received_at')->nullable()->after('head_submitted_at');
            $table->timestamp('enduser_received_at')->nullable()->after('ready_to_print_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropColumn(['head_received_at', 'enduser_received_at']);
        });
    }
};
