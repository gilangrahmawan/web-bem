<?php

namespace App\Services\Drive;

use App\Enums\DriveAccess;
use App\Models\Contracts\DriveItem;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan per item Drive (dipakai FolderPolicy, DriveFilePolicy, DocumentPolicy):
 * - lihat/unduh: akses ≥ view pada folder (untuk folder: dirinya; untuk berkas/dokumen: folder induknya).
 * - ganti nama, pindah, buang: folder induk manage, ATAU upload + item miliknya sendiri.
 *   Folder sistem (mis. "Surat") tidak pernah bisa diganti nama/dipindah/dibuang.
 * - edit isi dokumen: akses ≥ upload pada folder induk (kolaboratif) dan tidak dikunci user lain.
 * - bagikan folder: akses manage pada folder itu (Pembina tidak pernah).
 * - pulihkan dari Sampah: pemilik/pembuang item, manage pada folder asal, atau pengelola Drive.
 * - hapus permanen: manage pada folder asal atau pengelola Drive.
 */
class DriveGate
{
    public function __construct(private readonly DriveAccessResolver $access) {}

    public function view(User $user, DriveItem&Model $item): bool
    {
        $folder = $item instanceof Folder ? $item->getKey() : $item->driveFolderId();

        return $this->access->can($user, $folder, DriveAccess::View);
    }

    public function modify(User $user, DriveItem&Model $item): bool
    {
        if ($item instanceof Folder && $item->is_system) {
            return false;
        }

        $access = $this->access->accessFor($user, $item->driveFolderId());

        return $access === DriveAccess::Manage
            || ($access === DriveAccess::Upload && $item->driveOwnerId() === $user->getKey());
    }

    public function editDocument(User $user, Document $document): bool
    {
        return ! $document->trashed()
            && $this->access->can($user, $document->folder_id, DriveAccess::Upload)
            && ! $document->isLockedFor($user);
    }

    public function share(User $user, Folder $folder): bool
    {
        return ! $folder->trashed() && $this->access->accessFor($user, $folder) === DriveAccess::Manage;
    }

    public function restore(User $user, DriveItem&Model $item): bool
    {
        if (! $this->access->canUseDrive($user)) {
            return false;
        }

        return $this->access->isManager($user)
            || $item->getAttribute('deleted_by') === $user->getKey()
            || $item->driveOwnerId() === $user->getKey()
            || $this->access->accessFor($user, $item->driveFolderId()) === DriveAccess::Manage;
    }

    public function forceDelete(User $user, DriveItem&Model $item): bool
    {
        if ($item instanceof Folder && $item->is_system) {
            return false;
        }

        return $this->access->isManager($user)
            || $this->access->accessFor($user, $item->driveFolderId()) === DriveAccess::Manage;
    }

    /** Boleh menambah item (folder/berkas/dokumen) ke folder ini (null = root). */
    public function createIn(User $user, ?int $folderId): bool
    {
        return $this->access->can($user, $folderId, DriveAccess::Upload);
    }
}
