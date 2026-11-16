<?php

namespace App\Enums;

/**
 * Jenis notifikasi untuk preferensi push per user (keputusan Fase 9: toggle hanya mengatur push;
 * lonceng di dashboard selalu masuk). System = notifikasi teknis (selalu aktif, tidak bisa dimatikan).
 */
enum NotificationCategory: string
{
    case Tasks = 'tasks';
    case Kas = 'kas';
    case Announcements = 'announcements';
    case Posts = 'posts';
    case Aspirations = 'aspirations';
    case Activities = 'activities';
    case Recruitment = 'recruitment';
    case Drive = 'drive';
    case Chat = 'chat';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Tasks => 'Tugas konten',
            self::Kas => 'Kas & iuran',
            self::Announcements => 'Pengumuman & surat',
            self::Posts => 'Konten website',
            self::Aspirations => 'Aspirasi',
            self::Activities => 'Kegiatan (proposal & LPJ)',
            self::Recruitment => 'Oprec',
            self::Drive => 'Drive',
            self::Chat => 'Pesan chat baru',
            self::System => 'Sistem',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Tasks => 'Penugasan, pengingat H-2/H-1',
            self::Kas => 'Pembayaran tercatat, tunggakan, rekap bulanan',
            self::Announcements => 'Pengumuman Sekretaris & surat Humas',
            self::Posts => 'Menunggu persetujuan, revisi, terbit',
            self::Aspirations => 'Aspirasi baru, eskalasi, hasil kajian',
            self::Activities => 'Status proposal/LPJ, tenggat LPJ, pindah penanggung jawab',
            self::Recruitment => 'Pendaftar baru',
            self::Drive => 'Folder dibagikan kepada Anda',
            self::Chat => 'Pesan baru (kecuali percakapan yang di-mute)',
            self::System => 'Pemberitahuan teknis',
        };
    }

    /** @return list<self> kategori yang bisa diatur user */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c !== self::System));
    }
}
