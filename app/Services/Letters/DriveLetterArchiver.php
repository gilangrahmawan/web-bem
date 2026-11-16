<?php

namespace App\Services\Letters;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\DriveFile;
use App\Models\User;
use App\Notifications\DriveArchiveFailedNotification;
use App\Services\ActivityLogger;
use App\Services\Drive\DriveService;
use App\Services\Drive\SystemFolders;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Arsip otomatis surat Humas BER-LAMPIRAN ke Drive "Surat/{tahun}" (keputusan Fase 8):
 * lampiran DISALIN (berkas surat asli tidak diganggu) + satu Dokumen berisi judul, isi, pengirim,
 * tanggal, dan daftar penerima. Surat tanpa lampiran tidak diarsipkan.
 * Kegagalan (mis. kuota penuh) TIDAK menggagalkan pengiriman surat: dicatat di activity log dan
 * pengelola Drive (Sekretaris) diberi notifikasi.
 */
class DriveLetterArchiver implements LetterArchiver
{
    public function __construct(
        private readonly DriveService $drive,
        private readonly SystemFolders $folders,
        private readonly ActivityLogger $logger,
    ) {}

    public function archive(Announcement $letter): void
    {
        if ($letter->type !== AnnouncementType::Letter || blank($letter->attachment_path)) {
            return;
        }

        $imported = null;

        try {
            DB::transaction(function () use ($letter, &$imported): void {
                $folder = $this->folders->lettersYear((int) ($letter->created_at ?? now())->format('Y'));
                $sender = $letter->creator;

                $imported = $this->drive->importFile(null, $folder, Announcement::DISK, $letter->attachment_path, $letter->attachment_name ?: basename($letter->attachment_path));

                $document = new Document([
                    'folder_id' => $folder->getKey(),
                    'title' => mb_substr('Surat: '.$letter->title, 0, 200),
                    'has_letterhead' => false,
                ]);
                $document->forceFill([
                    'content' => $this->summary($letter),
                    'created_by' => $sender?->getKey(),
                    'updated_by' => $sender?->getKey(),
                ])->save();

                $this->drive->record($sender, 'archive', $document);
                $this->logger->log('drive.letter_archived', $letter, ['folder_id' => $folder->getKey(), 'document_id' => $document->getKey()], $sender, recordIp: false);
            });
        } catch (Throwable $e) {
            if ($imported !== null && DriveFile::withTrashed()->whereKey($imported->getKey())->doesntExist()) {
                Storage::disk($imported->disk)->delete(array_filter([$imported->path(), $imported->thumbnail_path]));
            }

            Log::warning('Arsip surat ke Drive gagal.', ['announcement_id' => $letter->getKey(), 'error' => $e->getMessage()]);
            $this->logger->log('drive.letter_archive_failed', $letter, ['error' => mb_substr($e->getMessage(), 0, 500)], recordIp: false);

            $managers = User::query()->where('is_active', true)->permission('folder.manage')->get();
            Notification::send($managers, new DriveArchiveFailedNotification($letter, $e->getMessage()));
        }
    }

    private function summary(Announcement $letter): string
    {
        $recipients = $letter->recipients()->with('user:id,name')->get()
            ->map(fn ($r) => $r->user?->name)->filter()->sort()->values();

        $list = $recipients->isEmpty()
            ? '<p>-</p>'
            : '<ul>'.$recipients->map(fn (string $name) => '<li>'.e($name).'</li>')->implode('').'</ul>';

        return '<h2>'.e($letter->title).'</h2>'
            .'<p><strong>Pengirim:</strong> '.e($letter->creator?->name ?? '-').'<br>'
            .'<strong>Tanggal:</strong> '.e(Format::date($letter->created_at)).', '.e((string) $letter->created_at?->format('H.i')).' WIB<br>'
            .'<strong>Lampiran:</strong> '.e((string) $letter->attachment_name).'</p>'
            .'<p>'.nl2br(e((string) $letter->body), false).'</p>'
            .'<h3>Penerima ('.$recipients->count().')</h3>'.$list;
    }
}
