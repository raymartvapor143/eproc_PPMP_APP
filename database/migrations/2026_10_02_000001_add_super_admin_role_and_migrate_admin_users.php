<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Modify role ENUM to include 'super_admin' along with existing roles
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('end_user', 'head', 'budget_officer', 'oppmo', 'twg', 'super_admin', 'admin', 'authorized_staff') DEFAULT 'end_user'");
        }

        // Migrate current 'admin' accounts to 'super_admin'
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any super_admin back to admin
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('end_user', 'head', 'budget_officer', 'oppmo', 'twg', 'admin', 'authorized_staff') DEFAULT 'end_user'");
        }
    }
};
