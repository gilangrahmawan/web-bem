<?php

namespace App\Models;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Anggota struktur kepengurusan per periode (halaman publik /struktur).
 * Hanya berisi data yang boleh tampil publik: nama, jabatan, prodi, foto.
 */
class StructureMember extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'period_id',
        'division_id',
        'user_id',
        'name',
        'position',
        'prodi',
        'photo',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::updated(function (StructureMember $member): void {
            if ($member->wasChanged('photo') && $old = $member->getOriginal('photo')) {
                Storage::disk('public')->delete($old);
            }
        });

        static::deleted(function (StructureMember $member): void {
            if ($member->photo) {
                Storage::disk('public')->delete($member->photo);
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoUrl(): string
    {
        return $this->photo
            ? Storage::disk('public')->url($this->photo)
            : InitialsAvatarProvider::forName($this->name);
    }
}
