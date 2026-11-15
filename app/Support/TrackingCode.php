<?php

namespace App\Support;

use Closure;
use RuntimeException;

/**
 * Kode tracking acak tak berurutan untuk form publik (AGENTS.md B4), mis. ASP-8K2X-QM7D.
 * Tanpa karakter ambigu 0, O, 1, I. Dipakai juga untuk PRP-, LPJ-, REG- (Fase 7).
 */
class TrackingCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param  Closure(string): bool  $exists  cek keunikan di tabel terkait
     */
    public static function generate(string $prefix, Closure $exists): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = $prefix.'-'.self::block().'-'.self::block();

            if (! $exists($code)) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode tracking unik.');
    }

    /** Normalisasi input pengguna: huruf besar, spasi dibuang. */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code) ?? '');
    }

    public static function isValidFormat(string $code, string $prefix): bool
    {
        $chars = preg_quote(self::ALPHABET, '/');

        return (bool) preg_match('/^'.preg_quote($prefix, '/')."-[{$chars}]{4}-[{$chars}]{4}$/", $code);
    }

    private static function block(): string
    {
        $block = '';

        for ($i = 0; $i < 4; $i++) {
            $block .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $block;
    }
}
