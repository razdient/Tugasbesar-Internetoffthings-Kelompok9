<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        Device::query()->firstOrCreate(
            ['device_id' => 'belt-01'],
            [
                'user_id' => $user->id,
                'name' => 'Smart Posture Belt 01',
                'angle_threshold' => 20,
                'duration_threshold' => 10,
            ]
        );
    }
}
