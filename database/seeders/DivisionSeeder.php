<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['slug' => 'bph', 'name' => 'BPH', 'color' => '#232361',
                'description' => 'Badan Pengurus Harian: Ketua, Wakil Ketua, Sekretaris, dan Bendahara.'],
            ['slug' => 'kajian', 'name' => 'Kajian', 'color' => '#0E7490',
                'description' => 'Divisi Kajian: pembahasan isu dan kajian strategis.'],
            ['slug' => 'medpub', 'name' => 'Media & Publikasi', 'color' => '#B45309',
                'description' => 'Divisi Media & Publikasi: konten website dan media sosial.'],
            ['slug' => 'humas', 'name' => 'Humas', 'color' => '#15803D',
                'description' => 'Divisi Hubungan Masyarakat: aspirasi, surat, dan relasi eksternal.'],
        ];

        foreach ($divisions as $division) {
            Division::query()->firstOrCreate(['slug' => $division['slug']], $division);
        }
    }
}
