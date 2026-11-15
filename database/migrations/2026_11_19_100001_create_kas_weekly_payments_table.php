<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran kas mingguan (AGENTS.md BAGIAN E). Satu baris = satu minggu lunas.
 * Batal bayar = baris dihapus (riwayat tercatat di activity_logs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_weekly_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->date('week_start');
            $table->unsignedInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Mencegah pembayaran ganda untuk minggu yang sama.
            $table->unique(['period_id', 'user_id', 'week_number']);
            $table->index(['period_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_weekly_payments');
    }
};
