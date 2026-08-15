<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'User Example',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        Table::create(['name' => 'Meja A', 'price_per_hour' => 50000]);
        Table::create(['name' => 'Meja B', 'price_per_hour' => 60000]);
        Table::create(['name' => 'Meja C', 'price_per_hour' => 75000]);
        Table::create(['name' => 'Meja VIP', 'price_per_hour' => 100000]);
    }
}
