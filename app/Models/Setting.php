<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan website yang bisa diubah dari dashboard (key → value JSON).
 * Gunakan Setting::get('cabinet_name') / Setting::set('cabinet_name', '...').
 */
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('value', 'key')->all(),
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    /** Dipakai saat settings diubah tanpa event model (mis. bem:import-data). */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
