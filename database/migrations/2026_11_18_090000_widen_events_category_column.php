<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom events.category semula string(20); kategori `university_anniversary` (22 karakter)
 * tidak muat. Harus berjalan sebelum migration data split_calendar_legend_categories.
 */
return new class extends Migration
{
    public const LENGTH = 40;

    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('category', self::LENGTH)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('category', 20)->change();
        });
    }
};
