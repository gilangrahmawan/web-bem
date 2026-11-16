<?php

namespace Database\Seeders;

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Services\Drive\SystemFolders;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Struktur Drive contoh (HANYA lokal): folder per divisi + izin berbagi + satu notulen dari template.
 * Tanpa berkas fisik. Hanya menambah; dilewati bila folder contoh sudah ada.
 */
class DemoDriveSeeder extends Seeder
{
    public const ROOT = 'Contoh — Program Kerja';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoDriveSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        if (Folder::query()->where('name', self::ROOT)->exists()) {
            $this->command?->warn('Drive demo sudah ada; dilewati.');

            return;
        }

        app(SystemFolders::class)->letters();
        $owner = User::query()->where('email', 'sekretaris@demo.test')->first();
        $divisions = Division::query()->pluck('id', 'slug');

        $make = function (string $name, ?Folder $parent = null) use ($owner): Folder {
            $folder = new Folder(['name' => $name, 'parent_id' => $parent?->getKey()]);
            $folder->forceFill(['created_by' => $owner?->getKey()])->save();

            return $folder;
        };

        $share = fn (Folder $f, DriveGranteeType $t, ?int $id, DriveAccess $a) => $id && FolderPermission::query()->firstOrCreate(
            ['folder_id' => $f->getKey(), 'grantee_type' => $t->value, 'grantee_id' => $id], ['access' => $a->value],
        );

        $root = $make(self::ROOT);
        $notulen = $make('Notulen Rapat', $root);

        foreach (['kajian' => 'Kajian', 'medpub' => 'Media & Publikasi', 'humas' => 'Humas'] as $slug => $label) {
            $folder = $make("Divisi {$label}", $root);
            $share($folder, DriveGranteeType::Division, $divisions[$slug] ?? null, DriveAccess::Upload);
            $share($notulen, DriveGranteeType::Division, $divisions[$slug] ?? null, DriveAccess::View);
        }

        $template = DocumentTemplate::query()->where('name', 'Notulen Rapat')->first();

        if ($template) {
            $doc = new Document(['folder_id' => $notulen->getKey(), 'title' => 'Notulen Rapat Pleno Perdana', 'has_letterhead' => true]);
            $doc->forceFill(['content' => $template->content, 'created_by' => $owner?->getKey(), 'updated_by' => $owner?->getKey()])->save();
        }

        $this->command?->info('Drive demo dibuat: folder per divisi (akses unggah), Notulen Rapat (akses lihat), folder Surat.');
    }
}
