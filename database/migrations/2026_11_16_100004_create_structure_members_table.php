<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data struktur kepengurusan per periode untuk halaman publik /struktur.
 * Terpisah dari users agar riwayat kabinet tersimpan dan email/HP akun tidak terekspos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('position');
            $table->string('prodi')->nullable();
            $table->string('photo')->nullable(); // disk public (WebP)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['period_id', 'division_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_members');
    }
};
