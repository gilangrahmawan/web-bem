<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9 (hanya menambah):
 * - users.notification_preferences (JSON): push per jenis notifikasi (null = semua aktif). Lonceng selalu masuk.
 * - setting chat_attachment_retention_months = 12 (lampiran chat dihapus otomatis setelahnya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable()->after('must_change_password');
        });

        if (! DB::table('settings')->where('key', 'chat_attachment_retention_months')->exists()) {
            DB::table('settings')->insert([
                'key' => 'chat_attachment_retention_months',
                'value' => json_encode(12),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });

        DB::table('settings')->where('key', 'chat_attachment_retention_months')->delete();
    }
};
