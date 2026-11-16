<?php

namespace App\Enums;

/**
 * Jenis berkas Drive (keputusan Fase 8): ikon, label, warna grafik, dan aturan upload
 * (ekstensi + MIME hasil deteksi isi). Dipakai untuk validasi mimes + mimetypes.
 */
enum DriveFileKind: string
{
    case Pdf = 'pdf';
    case Word = 'word';
    case Excel = 'excel';
    case PowerPoint = 'powerpoint';
    case Image = 'image';
    case Archive = 'archive';
    case Text = 'text';
    case Document = 'document'; // dokumen editor internal (bukan berkas unggahan)

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Word => 'Word',
            self::Excel => 'Excel',
            self::PowerPoint => 'PowerPoint',
            self::Image => 'Gambar',
            self::Archive => 'ZIP',
            self::Text => 'Teks/CSV',
            self::Document => 'Dokumen',
        };
    }

    /** Heroicon (outline) untuk kartu/daftar Drive. */
    public function icon(): string
    {
        return match ($this) {
            self::Pdf => 'heroicon-o-document-text',
            self::Word, self::Document => 'heroicon-o-document',
            self::Excel => 'heroicon-o-table-cells',
            self::PowerPoint => 'heroicon-o-presentation-chart-bar',
            self::Image => 'heroicon-o-photo',
            self::Archive => 'heroicon-o-archive-box',
            self::Text => 'heroicon-o-bars-3-bottom-left',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Pdf => '#DC2626',
            self::Word, self::Document => '#000078',
            self::Excel => '#15803D',
            self::PowerPoint => '#C2410C',
            self::Image => '#FFDB1A',
            self::Archive => '#232361',
            self::Text => '#B6B6B7',
        };
    }

    /** Ekstensi yang diizinkan per jenis (unggahan). @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Word => ['doc', 'docx'],
            self::Excel => ['xls', 'xlsx'],
            self::PowerPoint => ['ppt', 'pptx'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp'],
            self::Archive => ['zip'],
            self::Text => ['txt', 'csv'],
            self::Document => [],
        };
    }

    /** MIME (deteksi isi oleh finfo) yang diterima per jenis. @return list<string> */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['application/pdf'],
            self::Word => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
            self::Excel => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
            self::PowerPoint => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
            self::Image => ['image/jpeg', 'image/png', 'image/webp'],
            self::Archive => ['application/zip', 'application/x-zip-compressed'],
            self::Text => ['text/plain', 'text/csv', 'application/csv'],
            self::Document => [],
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower($extension);

        foreach (self::cases() as $kind) {
            if (in_array($extension, $kind->extensions(), true)) {
                return $kind;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return array_values(array_merge(...array_map(fn (self $k) => $k->extensions(), self::cases())));
    }

    /** @return list<string> */
    public static function allowedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (self $k) => $k->mimeTypes(), self::cases()))));
    }

    /** Pratinjau di browser: gambar inline, PDF lewat viewer bawaan browser. */
    public function previewable(): bool
    {
        return in_array($this, [self::Image, self::Pdf], true);
    }
}
