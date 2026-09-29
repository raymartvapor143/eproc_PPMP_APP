<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or update the system administrator account
        User::updateOrCreate(
            ['email' => 'admin@system16.com'],
            [
                'name'         => 'System Administrator',
                'password'     => Hash::make('++admin@2026.davsur++'),
                'role'         => 'admin',
                'designation'  => 'System Administrator',
                'is_active'    => true,
            ]
        );
    }
}
