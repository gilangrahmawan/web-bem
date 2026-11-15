<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanggung jawab tugas konten Medpub (agenda kategori `content`).
 * Berkas hasil disimpan di disk privat dengan nama UUID; nama asli di result_original_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('todo')->index();
            $table->string('result_path')->nullable();
            $table->string('result_original_name')->nullable();
            $table->string('result_url', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reminded_h2_at')->nullable();
            $table->timestamp('reminded_h1_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_assignees');
    }
};
