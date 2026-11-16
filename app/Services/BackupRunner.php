<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menjalankan backup dari halaman Backup (proses web) dan menerjemahkan kegagalan menjadi pesan yang
 * spesifik — TANPA membocorkan password (database, arsip backup, atau argumen perintah).
 */
class BackupRunner
{
    /** @return array{ok: bool, message: string} */
    public function runDatabaseBackup(): array
    {
        // Pemeriksaan awal: db-dumper menulis kredensial MySQL ke berkas sementara (tmpfile()).
        if (! $this->temporaryDirectoryWritable()) {
            return ['ok' => false, 'message' => $this->explain('tempFileHandle')];
        }

        try {
            $exit = Artisan::call('backup:run', ['--only-db' => true]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => $this->explain($e->getMessage())];
        }

        if ($exit === 0) {
            return ['ok' => true, 'message' => 'Backup database selesai.'];
        }

        preg_match('/Backup failed because:\s*(.+)/i', $output, $m);

        return ['ok' => false, 'message' => $this->explain($m[1] ?? $output)];
    }

    public function temporaryDirectoryWritable(): bool
    {
        $handle = @tmpfile();

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        return true;
    }

    /** Penyebab umum → saran perbaikan; selain itu pesan asli yang sudah disensor. */
    public function explain(string $raw): string
    {
        $reason = $this->redact(trim($raw));

        $hint = match (true) {
            Str::contains($reason, 'tempFileHandle') => 'Folder sementara sistem (TEMP/TMP) tidak bisa ditulisi oleh proses web. '
                .'Lokal dengan "php artisan serve": hentikan lalu jalankan ulang server (TEMP/TMP kini diteruskan). '
                .'Di hosting: minta pengelola hosting memastikan folder /tmp dapat ditulisi PHP.',
            Str::contains($reason, ['is not recognized', 'not found', 'No such file', 'tidak dikenali', 'cannot find the path'])
                && Str::contains(Str::lower($reason), 'mysqldump') => 'Program mysqldump tidak ditemukan. Isi DB_DUMP_BINARY_PATH di .env '
                .'dengan folder mysqldump (XAMPP: C:/xampp/mysql/bin), lalu jalankan php artisan config:clear.',
            Str::contains($reason, 'Access denied') => 'Database menolak login mysqldump. Periksa DB_USERNAME/DB_PASSWORD di .env '
                .'dan hak akses user database (perlu izin SELECT, LOCK TABLES, SHOW VIEW).',
            Str::contains($reason, ['disk', 'Disk']) && Str::contains($reason, ['not', 'tidak']) => 'Disk tujuan backup tidak tersedia. '
                .'Periksa BACKUP_DISK di .env (backups atau r2) dan kredensial R2 bila dipakai.',
            Str::contains($reason, ['Permission denied', 'failed to open stream', 'not writable']) => 'Folder storage/backups atau '
                .'storage/app/backup-temp tidak bisa ditulisi. Periksa izin folder storage.',
            default => null,
        };

        $reason = Str::limit($reason, 240);

        return $hint ? "{$hint} (Detail: {$reason})" : "Penyebab: {$reason}";
    }

    /** Sensor password & argumen sensitif dari pesan galat. */
    public function redact(string $text): string
    {
        $secrets = array_filter([
            (string) config('database.connections.'.config('database.default').'.password'),
            (string) config('backup.backup.password'),
            (string) config('filesystems.disks.r2.secret'),
        ], fn (string $s) => strlen($s) >= 3);

        foreach ($secrets as $secret) {
            $text = str_replace($secret, '******', $text);
        }

        $text = preg_replace('/(?<![\w-])(--password=|-p)(\'[^\']*\'|"[^"]*"|\S+)/', '$1******', $text) ?? $text;
        $text = preg_replace('/(password\s*[=:]\s*)(\'[^\']*\'|"[^"]*"|\S+)/i', '$1******', $text) ?? $text;

        // Tanpa path lengkap server (cukup nama berkas/folder terakhir).
        return preg_replace('#[A-Za-z]:[\\\\/][^\s\'"]*[\\\\/]([^\\\\/\s\'"]+)#', '…/$1', $text) ?? $text;
    }
}
