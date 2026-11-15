<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal mulai wajib kas per pengurus (opsional), untuk yang bergabung belakangan.
 * Kewajiban efektif = tanggal yang lebih akhir antara setting kas_start_date dan kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('kas_starts_on')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kas_starts_on');
        });
    }
};
