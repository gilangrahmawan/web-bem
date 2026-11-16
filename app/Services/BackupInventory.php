<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** Daftar berkas backup di disk tujuan (untuk halaman Backup, unduhan, dan pengingat bulanan). */
class BackupInventory
{
    public function diskName(): string
    {
        return (string) (config('backup.backup.destination.disks')[0] ?? 'backups');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function folder(): string
    {
        return (string) config('backup.backup.name');
    }

    public function usesOffsiteStorage(): bool
    {
        return $this->diskName() !== 'backups';
    }

    /** @return Collection<int, array{path: string, name: string, size: int, date: Carbon}> terbaru dulu */
    public function files(): Collection
    {
        return collect($this->disk()->files($this->folder()))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) $this->disk()->size($path),
                'date' => Carbon::createFromTimestamp($this->disk()->lastModified($path)),
            ])
            ->sortByDesc('date')
            ->values();
    }

    /** Path aman: hanya berkas .zip langsung di folder backup (cegah path traversal). */
    public function resolve(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name)) {
            return null;
        }

        $path = $this->folder().'/'.$name;

        return $this->disk()->exists($path) ? $path : null;
    }
}
