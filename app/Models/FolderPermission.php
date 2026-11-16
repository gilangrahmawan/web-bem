<?php

namespace App\Models;

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Enums\RoleName;
use App\Services\Drive\DriveAccessResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/** Izin berbagi folder ke role/divisi/pengurus (diwariskan ke semua subfolder). */
class FolderPermission extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'grantee_type',
        'grantee_id',
        'access',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'grantee_type' => DriveGranteeType::class,
            'access' => DriveAccess::class,
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(DriveAccessResolver::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function granteeLabel(): string
    {
        return match ($this->grantee_type) {
            DriveGranteeType::Role => 'Role: '.RoleName::labelFor((string) Role::query()->whereKey($this->grantee_id)->value('name')),
            DriveGranteeType::Division => 'Divisi: '.(Division::query()->whereKey($this->grantee_id)->value('name') ?? '-'),
            DriveGranteeType::User => User::query()->whereKey($this->grantee_id)->value('name') ?? 'Pengurus (dihapus)',
        };
    }
}
