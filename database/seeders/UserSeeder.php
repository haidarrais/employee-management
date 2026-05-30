<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $this->createUserIfNotExists(
            'Administrator',
            'admin@example.com',
            'admin123',
            'admin'
        );

        // Create management user
        $this->createUserIfNotExists(
            'Manager',
            'manager@example.com',
            'manager123',
            'management'
        );

        // Create employee user
        $this->createUserIfNotExists(
            'Employee',
            'employee@example.com',
            'employee123',
            'employee'
        );
    }

    /**
     * Create a user if they don't already exist.
     */
    private function createUserIfNotExists(string $name, string $email, string $password, string $role): void
    {
        $exists = User::where('email', $email)->exists();

        if (!$exists) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'role' => $role,
                'mfa_enabled' => false,
                'mfa_secret' => null,
                'is_active' => true,
            ]);

            $this->command->info("{$role} user created: {$email}");
        } else {
            $this->command->info("User already exists: {$email}");
        }
    }
}