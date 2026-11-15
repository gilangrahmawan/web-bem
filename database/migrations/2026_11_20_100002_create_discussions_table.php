<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tabel pembahasan Divisi Kajian (AGENTS.md C3 Kajian, BAGIAN E). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('photo')->nullable();
            $table->json('reference_links')->nullable();
            $table->string('docs_link', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('aspiration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
