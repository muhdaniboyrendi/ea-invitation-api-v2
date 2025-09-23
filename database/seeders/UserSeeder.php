<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Muhdani Boyrendi Erlan Azhari',
            'email' => 'erlanazrdev@gmail.com',
            'phone' => '082220633024',
            'password' => '12345678',
            'role' => 'admin',
        ]);
    }
}
