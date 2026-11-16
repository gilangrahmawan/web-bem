<?php

namespace App\Models\Contracts;

use App\Enums\DriveItemType;

/** Item yang tampil di Drive: folder, berkas unggahan, atau dokumen editor. */
interface DriveItem
{
    public function driveType(): DriveItemType;

    public function driveName(): string;

    /** Folder induk (null = root Drive). */
    public function driveFolderId(): ?int;

    /** Pembuat/pengunggah item (untuk aturan "upload: hanya miliknya sendiri"). */
    public function driveOwnerId(): ?int;
}
