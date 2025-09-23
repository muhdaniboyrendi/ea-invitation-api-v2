<?php

namespace Database\Seeders;

use App\Models\ThemeCategory;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ThemeCategory::create([
            'name' => 'Basic',
        ]);

        ThemeCategory::create([
            'name' => 'Modern',
        ]);

        ThemeCategory::create([
            'name' => 'Social',
        ]);
    }
}
