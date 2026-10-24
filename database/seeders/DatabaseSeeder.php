<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Creates the login user. Set ADMIN_EMAIL / ADMIN_PASSWORD in .env before seeding. */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD') ?: Str::password(12, symbols: false);

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Login user $email already exists.");

            return;
        }

        User::create([
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->command?->warn("Login: $email / $password  (change it in Settings after logging in)");
    }
}
