<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Nilai awal pengaturan. Hanya mengisi kunci yang belum ada,
 * sehingga tidak menimpa perubahan yang dibuat dari dashboard.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'kas_weekly_amount' => 5000,
            'cabinet_name' => 'Kabinet (isi nama kabinet)',
            'vision' => 'Visi BEM UBHINUS (isi dari dashboard: Pengaturan → Pengaturan Website).',
            'mission' => "Misi pertama\nMisi kedua\nMisi ketiga",
            'leader_welcome' => 'Sambutan Ketua BEM UBHINUS (isi dari dashboard).',
            'contact_email' => null,
            'contact_phone' => null,
            'contact_address' => 'Universitas Bhinneka Nusantara, Malang',
            'social_instagram' => null,
            'social_tiktok' => null,
            'social_youtube' => null,
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
