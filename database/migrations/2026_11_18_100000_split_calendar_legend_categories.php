<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration DATA (tanpa perubahan skema): memisahkan kategori sesuai legenda kalender BEM.
 *
 * - holiday berjudul "… (Cuti Bersama)"  → collective_leave (akhiran judul dihapus)
 * - commemoration berjudul "Hari Jadi …" → regional_anniversary
 * - campus berjudul "HUT UBHINUS…"       → university_anniversary
 *
 * Hanya memindahkan baris yang cocok; kategori lain tidak disentuh. Bisa di-rollback.
 */
return new class extends Migration
{
    private const SUFFIX = ' (Cuti Bersama)';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('events')
                ->where('category', 'holiday')
                ->where('title', 'like', '%'.self::SUFFIX)
                ->orderBy('id')
                ->get(['id', 'title'])
                ->each(fn (object $row) => DB::table('events')->where('id', $row->id)->update([
                    'category' => 'collective_leave',
                    'title' => substr($row->title, 0, -strlen(self::SUFFIX)),
                ]));

            DB::table('events')
                ->where('category', 'commemoration')
                ->where('title', 'like', 'Hari Jadi %')
                ->update(['category' => 'regional_anniversary']);

            DB::table('events')
                ->where('category', 'campus')
                ->where('title', 'like', 'HUT UBHINUS%')
                ->update(['category' => 'university_anniversary']);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('events')
                ->where('category', 'collective_leave')
                ->orderBy('id')
                ->get(['id', 'title'])
                ->each(fn (object $row) => DB::table('events')->where('id', $row->id)->update([
                    'category' => 'holiday',
                    'title' => $row->title.self::SUFFIX,
                ]));

            DB::table('events')->where('category', 'regional_anniversary')->update(['category' => 'commemoration']);
            DB::table('events')->where('category', 'university_anniversary')->update(['category' => 'campus']);
        });
    }
};
