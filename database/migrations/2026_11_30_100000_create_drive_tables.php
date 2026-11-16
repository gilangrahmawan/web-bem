<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drive internal & dokumen (AGENTS.md Fase 8, keputusan "Drive mirip Google Drive").
 * Berkas fisik di disk privat; semua akses lewat controller berotorisasi.
 * trash_batch: item yang dibuang bersama (folder + isinya) dipulihkan bersama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('parent_id')->nullable()->constrained('folders')->restrictOnDelete();
            $table->boolean('is_system')->default(false);
            $table->string('system_key', 50)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('trash_batch')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'deleted_at']);
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('disk', 30)->default('local');
            $table->string('mime_type', 150);
            $table->string('extension', 10);
            $table->string('kind', 20)->index();
            $table->unsignedBigInteger('size');
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('trash_batch')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['folder_id', 'deleted_at']);
        });

        Schema::create('folder_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->string('grantee_type', 20);
            $table->unsignedBigInteger('grantee_id');
            $table->string('access', 10);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['folder_id', 'grantee_type', 'grantee_id'], 'folder_permissions_grantee_unique');
            $table->index(['grantee_type', 'grantee_id']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->restrictOnDelete();
            $table->string('title', 200);
            $table->longText('content')->nullable();
            $table->boolean('has_letterhead')->default(false);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('trash_batch')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['folder_id', 'deleted_at']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['document_id', 'saved_by', 'created_at']);
        });

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->longText('content');
            $table->boolean('has_letterhead')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('drive_stars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            $table->timestamps();
            $table->unique(['user_id', 'item_type', 'item_id']);
        });

        Schema::create('drive_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            $table->string('item_name', 255);
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['item_type', 'item_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_activities');
        Schema::dropIfExists('drive_stars');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('folder_permissions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folders');
    }
};
