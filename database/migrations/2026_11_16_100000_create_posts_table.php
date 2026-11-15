<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();          // PostType
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();         // HTML, disanitasi saat disimpan & ditampilkan
            $table->string('cover')->nullable();          // disk public (WebP)
            $table->text('social_caption')->nullable();
            $table->string('category', 30)->nullable()->index(); // PostCategory
            $table->string('attachment_path')->nullable(); // lampiran PDF (disk public, konten terbit)
            $table->string('attachment_name')->nullable(); // nama asli lampiran
            $table->string('status', 30)->default('draft')->index(); // PostStatus
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            // FK ke event_assignees ditambahkan di Fase 3/6 setelah tabelnya ada.
            $table->unsignedBigInteger('event_assignee_id')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('social_posted_at')->nullable();
            $table->string('social_post_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
