<?php

namespace Database\Factories;

use App\Enums\DriveFileKind;
use App\Models\DriveFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas Drive + isi fisik kecil di disk 'local' (pakai Storage::fake('local') di test).
 *
 * @extends Factory<DriveFile>
 */
class DriveFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folder_id' => null,
            'original_name' => fake()->words(2, true).'.pdf',
            'stored_name' => Str::uuid().'.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'kind' => DriveFileKind::Pdf,
            'size' => 1024,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (DriveFile $file): void {
            Storage::disk($file->disk)->put($file->path(), str_repeat('x', (int) $file->size));
        });
    }
}
