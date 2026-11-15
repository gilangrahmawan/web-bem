<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar inisial yang dibuat lokal (SVG data URI).
 * Pengganti ui-avatars.com agar nama pengurus tidak dikirim ke layanan pihak ketiga.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return self::forName((string) Filament::getNameForDefaultAvatar($record));
    }

    public static function forName(string $name): string
    {
        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->map(fn (string $part): string => (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        $initials = htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#232361"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFDB1A" '
            .'font-family="Arial, sans-serif" font-size="26" font-weight="700">'.$initials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
