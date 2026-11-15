<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DivisionSeeder::class,
            SettingSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            HolidaySeeder::class,
            CalendarCsvSeeder::class,
        ]);

        // Akun uji hanya di lokal. Jangan pernah di produksi.
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
