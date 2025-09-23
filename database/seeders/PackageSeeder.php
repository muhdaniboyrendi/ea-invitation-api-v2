<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Package::create([
            'name' => 'Economy',
            'price' => 100000,
            'features' => [
                '100 tamu undangan + grup',
                '4 foto galeri (max)',
                'Informasi acara',
                'Background musik (list)',
                'Timer countdown',
                'Maps lokasi',
                'Story',
                'RSVP',
                'Ucapan tamu',
                '1 bulan masa aktif'
            ]
        ]);

        Package::create([
            'name' => 'Premium',
            'price' => 150000,
            'features' => [
                '500 tamu undangan + grup',
                '10 foto galeri (max)',
                '1 video',
                'Informasi acara',
                'Background musik custom',
                'Timer countdown',
                'Maps lokasi',
                'Tambah ke kalender',
                'Story',
                'RSVP',
                'Ucapan tamu',
                'Kirim hadiah',
                '6 bulan masa aktif'
            ]
        ]);

        Package::create([
            'name' => 'Business',
            'price' => 250000,
            'features' => [
                'Unlimited tamu undangan + grup',
                '50 foto galeri (max)',
                '10 video (max)',
                'Informasi acara',
                'Background musik custom',
                'Timer countdown',
                'Maps lokasi',
                'Tambah ke kalender',
                'Story',
                'RSVP',
                'Ucapan tamu',
                'Kirim hadiah',
                '6 bulan masa aktif'
            ]
        ]);
    }
}
