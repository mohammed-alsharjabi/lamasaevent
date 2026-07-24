<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            if (app()->environment('production', 'staging')) {
                throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD are required.');
            }

            $this->command?->warn('Admin seed skipped: set ADMIN_EMAIL and ADMIN_PASSWORD.');

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => env('ADMIN_NAME', 'مدير الموقع'),
            'password' => $password,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
