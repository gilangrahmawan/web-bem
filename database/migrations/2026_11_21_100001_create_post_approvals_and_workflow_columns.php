<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persetujuan konten (AGENTS.md Fase 6).
 * - post_approvals: keputusan per tahap (coordinator/leader/publication) per putaran review.
 * - posts.review_round: naik setiap kali konten diajukan; penerbitan hanya menghitung persetujuan
 *   di putaran yang sama (revisi → kedua tahap wajib disetujui ulang).
 * - posts.cover_original_*: gambar asli resolusi penuh di disk privat (untuk medsos), cover WebP tetap untuk website.
 * - FK posts.event_assignee_id → event_assignees (kolom dibuat di Fase 2 sebelum tabelnya ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round')->default(1);
            $table->string('stage', 20);
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'round', 'stage']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('review_round')->default(0)->after('status');
            $table->string('cover_original_path')->nullable()->after('cover');
            $table->string('cover_original_name')->nullable()->after('cover_original_path');
        });

        // Nilai yatim (bila ada) dikosongkan dulu agar FK bisa dibuat.
        DB::table('posts')
            ->whereNotNull('event_assignee_id')
            ->whereNotIn('event_assignee_id', DB::table('event_assignees')->select('id'))
            ->update(['event_assignee_id' => null]);

        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('event_assignee_id')->references('id')->on('event_assignees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['event_assignee_id']);
            $table->dropColumn(['review_round', 'cover_original_path', 'cover_original_name']);
        });

        Schema::dropIfExists('post_approvals');
    }
};
