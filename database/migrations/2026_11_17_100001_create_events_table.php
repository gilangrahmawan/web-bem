<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda kalender internal & eksternal (AGENTS.md C4, BAGIAN E).
 * ends_at bersifat inklusif; null = agenda satu waktu/hari (ends_at = starts_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 20)->index();
            $table->string('visibility', 20)->index();
            $table->foreignId('division_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(true);
            $table->string('location', 200)->nullable();
            $table->string('organizer', 150)->nullable();
            $table->string('registration_url', 500)->nullable();
            $table->boolean('has_conflict')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['visibility', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
