<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisasi pengaju kegiatan (keputusan Fase 7). Kegiatan milik organisasi, bukan milik orang,
 * sehingga riwayatnya tetap ada walau akun pengurusnya berganti.
 * Organisasi "BEM UBHINUS" dibuat otomatis untuk kegiatan internal BEM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('type', 20)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
        });

        DB::table('organizations')->insert([
            'name' => 'BEM UBHINUS',
            'type' => 'bem',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
