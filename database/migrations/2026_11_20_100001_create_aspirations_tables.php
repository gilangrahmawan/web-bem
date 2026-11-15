<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aspirasi publik (AGENTS.md BAGIAN E). Aspirasi anonim tidak menyimpan identitas maupun IP.
 * - response: tanggapan untuk pengirim (tampil di cek status).
 * - rejection_reason: alasan penolakan untuk pengirim (tampil di cek status).
 * - aspiration_logs.note: catatan INTERNAL (tidak pernah tampil ke publik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aspirations', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code', 20)->unique();
            $table->string('name', 100)->nullable();
            $table->string('contact', 100)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('category', 30)->index();
            $table->text('body');
            $table->string('status', 20)->default('new')->index();
            $table->text('response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('aspiration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aspiration_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->index();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspiration_logs');
        Schema::dropIfExists('aspirations');
    }
};
