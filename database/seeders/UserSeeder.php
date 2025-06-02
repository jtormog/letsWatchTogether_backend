<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Usuario de Prueba',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // Create some social auth users for testing
        User::firstOrCreate(
            ['email' => 'google@example.com'],
            [
                'name' => 'Usuario Google',
                'provider' => 'google',
                'provider_id' => '123456789',
                'avatar' => 'https://via.placeholder.com/150',
                'email_verified_at' => now(),
                'password' => Hash::make('random_password'),
            ]
        );

        // Create additional test users using factory
        User::factory(10)->create();
    }
}
