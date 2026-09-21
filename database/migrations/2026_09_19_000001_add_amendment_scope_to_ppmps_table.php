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
            $table->string('requested_amendment_scope', 50)->nullable()->after('requested_amendment_reason');
            $table->string('amendment_scope', 50)->nullable()->after('amendment_reason');
            // Change amendment_type to string if previously enum to cleanly accept ATTACHMENT_LIST or other scopes
            $table->string('amendment_type', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropColumn(['requested_amendment_scope', 'amendment_scope']);
        });
    }
};
