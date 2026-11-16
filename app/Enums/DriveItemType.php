<?php

namespace App\Enums;

/** Jenis item Drive (bintang, aktivitas, sampah). */
enum DriveItemType: string
{
    case Folder = 'folder';
    case File = 'file';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Folder => 'Folder',
            self::File => 'Berkas',
            self::Document => 'Dokumen',
        };
    }
}
