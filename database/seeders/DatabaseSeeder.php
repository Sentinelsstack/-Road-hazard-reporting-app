<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('Admin@12345678'),
        ]);
        $admin->role = 'admin';
        $admin->save();

        User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('User@12345678'),
        ]);

        $this->call(HazardSeeder::class);
    }
}
