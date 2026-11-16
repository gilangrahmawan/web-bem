<?php

namespace App\Services\Drive;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Dokumen Drive (AGENTS.md Fase 8): buat (kosong/dari template), simpan (auto-save ±10 detik),
 * riwayat versi (maks. 1 per menit per user), kunci edit 15 menit, pulihkan versi.
 * Isi disanitasi di model (profil purifier 'document') saat disimpan dan saat ditampilkan.
 */
class DocumentService
{
    public const VERSION_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly DriveGate $gate,
        private readonly DriveService $drive,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(User $actor, ?int $folderId, string $title, ?int $templateId = null): Document
    {
        if ($folderId !== null && Folder::query()->whereKey($folderId)->doesntExist()) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak ditemukan.']);
        }

        if (! $this->gate->createIn($actor, $folderId)) {
            throw new AuthorizationException('Anda tidak punya akses unggah di folder ini.');
        }

        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['title' => 'Judul wajib diisi (maks. 200 karakter).']);
        }

        $template = $templateId ? DocumentTemplate::query()->find($templateId) : null;

        return DB::transaction(function () use ($actor, $folderId, $title, $template): Document {
            $document = new Document(['folder_id' => $folderId, 'title' => $title, 'has_letterhead' => $template?->has_letterhead ?? false]);
            $document->forceFill([
                'content' => $template?->content ?? '<p></p>',
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->drive->record($actor, 'create', $document);

            return $document;
        });
    }

    /** Ambil/perpanjang kunci edit. false bila sedang dikunci user lain (atau tidak berhak edit). */
    public function acquireLock(User $actor, Document $document): bool
    {
        return DB::transaction(function () use ($actor, $document): bool {
            $fresh = Document::query()->lockForUpdate()->find($document->getKey());

            if (! $fresh || Gate::forUser($actor)->denies('edit', $fresh)) {
                return false;
            }

            $fresh->forceFill(['locked_by' => $actor->getKey(), 'locked_at' => now()])->saveQuietly();
            $document->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    public function releaseLock(User $actor, Document $document): void
    {
        Document::query()->whereKey($document->getKey())->where('locked_by', $actor->getKey())
            ->update(['locked_by' => null, 'locked_at' => null]);
    }

    /**
     * Simpan isi dokumen. Ditolak bila dikunci user lain. Versi baru dibuat paling banyak
     * 1 per menit per user (auto-save yang lebih sering hanya memperbarui isi dokumen).
     */
    public function save(User $actor, Document $document, string $content, ?string $title = null): Document
    {
        return DB::transaction(function () use ($actor, $document, $content, $title): Document {
            $fresh = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($fresh->isLockedFor($actor)) {
                throw ValidationException::withMessages(['content' => 'Dokumen sedang diedit oleh '.($fresh->locker?->name ?? 'pengurus lain').'. Perubahan Anda tidak disimpan.']);
            }

            if (Gate::forUser($actor)->denies('edit', $fresh)) {
                throw new AuthorizationException('Anda tidak berhak mengedit dokumen ini.');
            }

            $sanitized = Document::sanitize($content);
            $changed = $sanitized !== (string) $fresh->content;

            if ($title !== null && trim($title) !== '' && trim($title) !== $fresh->title) {
                $fresh->title = mb_substr(trim($title), 0, 200);
                $changed = true;
            }

            $fresh->forceFill([
                'content' => $sanitized,
                'updated_by' => $changed ? $actor->getKey() : $fresh->updated_by,
                'locked_by' => $actor->getKey(),
                'locked_at' => now(),
            ])->save();

            if ($changed) {
                $this->maybeVersion($actor, $fresh);
            }

            $document->setRawAttributes($fresh->getAttributes(), true);

            return $document;
        });
    }

    public function restoreVersion(User $actor, Document $document, DocumentVersion $version): Document
    {
        if ($version->document_id !== $document->getKey()) {
            throw ValidationException::withMessages(['version' => 'Versi tidak cocok dengan dokumen.']);
        }

        $restored = $this->save($actor, $document, (string) $version->content);

        // Pemulihan selalu tercatat sebagai versi tersendiri (tidak terkena batas 1/menit).
        $restored->versions()->create(['content' => $restored->content, 'saved_by' => $actor->getKey()]);
        $this->logger->log('document.version_restored', $document, ['version_id' => $version->getKey()], $actor);

        return $restored;
    }

    private function maybeVersion(User $actor, Document $document): void
    {
        $recent = DocumentVersion::query()
            ->where('document_id', $document->getKey())
            ->where('saved_by', $actor->getKey())
            ->where('created_at', '>', now()->subSeconds(self::VERSION_INTERVAL_SECONDS))
            ->latest('id')
            ->first();

        if ($recent) {
            $recent->update(['content' => $document->content]);

            return;
        }

        $document->versions()->create(['content' => $document->content, 'saved_by' => $actor->getKey()]);
        $this->drive->record($actor, 'edit', $document);
    }
}
