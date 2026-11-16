<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat (AGENTS.md C6 + keputusan Fase 9).
 * - personal_key: "minId:maxId" agar percakapan 1:1 tidak dobel.
 * - system_key: grup otomatis ("division:kajian", "all_staff", "organization:5").
 * - messages.body disimpan TERENKRIPSI (cast encrypted, APP_KEY) → kolom text.
 * - Pesan dihapus: soft delete + body/lampiran dikosongkan (benar-benar hilang dari server).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name', 100)->nullable();
            $table->string('system_key', 60)->nullable()->unique();
            $table->string('personal_key', 40)->nullable()->unique();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10)->default('member');
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamp('attachment_expired_at')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }
};
