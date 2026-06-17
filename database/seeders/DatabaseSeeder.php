<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'      => 'System Admin',
            'email'     => 'admin@villa.com',
            'password'  => Hash::make('password123'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Manager',
            'email'     => 'manager@villa.com',
            'password'  => Hash::make('password123'),
            'role'      => 'manager',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Staff User',
            'email'     => 'staff@villa.com',
            'password'  => Hash::make('password123'),
            'role'      => 'staff',
            'is_active' => true,
        ]);
    }
}
