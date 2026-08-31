<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password', 'role' => 'admin']
        );

        User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'User Example', 'password' => 'password', 'role' => 'user']
        );

        // Hapus meja lama dulu agar tidak duplikat jika perlu, atau gunakan logic serupa
        Table::truncate();
        Table::create(['name' => 'Meja A', 'price_per_hour' => 50000]);
        Table::create(['name' => 'Meja B', 'price_per_hour' => 60000]);
        Table::create(['name' => 'Meja C', 'price_per_hour' => 75000]);
        Table::create(['name' => 'Meja VIP', 'price_per_hour' => 100000]);
    }
}
