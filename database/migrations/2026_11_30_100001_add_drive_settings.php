<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration DATA (hanya menambah, keputusan Fase 8): kuota Drive global 2 GB & masa simpan Sampah 30 hari.
 * Folder sistem "Surat" + izin default-nya dibuat oleh App\Services\Drive\SystemFolders::ensure()
 * (bukan di sini, karena di instalasi baru role/divisi baru ada setelah seeder).
 */
return new class extends Migration
{
    private const SETTINGS = [
        'drive_quota_mb' => 2048,
        'drive_trash_days' => 30,
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
    }
};
