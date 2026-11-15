<?php

namespace Database\Seeders;

use App\Enums\DueType;
use App\Models\Due;
use App\Models\KasWeeklyPayment;
use App\Models\User;
use App\Services\KasService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data kas contoh untuk akun demo saja (email @demo.test). HANYA UNTUK LOKAL.
 *
 * Sengaja TIDAK mengubah setting global (nominal / tanggal mulai kas): tanggal mulai kas
 * diisi sendiri oleh Bendahara/Super Admin. Bila tanggal mulai kas sudah diatur, seeder ini
 * mengisi pembayaran acak untuk akun demo; bila belum, hanya membuat iuran contoh.
 */
class DemoKasSeeder extends Seeder
{
    public function run(KasService $kas): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoKasSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        $demoUsers = User::query()->where('email', 'like', '%@demo.test')->pluck('id');
        $bendahara = User::query()->where('email', 'bendahara@demo.test')->first();

        if ($demoUsers->isEmpty() || $bendahara === null) {
            $this->command?->warn('DemoKasSeeder dilewati: akun demo belum ada.');

            return;
        }

        if (! Due::query()->where('name', 'like', 'Contoh:%')->exists()) {
            $due = Due::query()->create([
                'name' => 'Contoh: PDH Kabinet',
                'amount' => 150000,
                'type' => DueType::Once,
                'due_date' => now()->addMonth()->toDateString(),
                'applies_to_all' => false,
                'created_by' => $bendahara->id,
            ]);
            $due->members()->sync($kas->payersQuery()->whereIn('users.id', $demoUsers)->pluck('users.id'));
        }

        if ($kas->setupProblem() !== null) {
            $this->command?->warn('Pembayaran kas demo dilewati: '.$kas->setupProblem().' (atur di Kas Mingguan → Pengaturan kas).');

            return;
        }

        if (KasWeeklyPayment::query()->whereIn('user_id', $demoUsers)->exists()) {
            $this->command?->warn('Pembayaran kas demo sudah ada; dilewati.');

            return;
        }

        $current = $kas->currentWeekNumber();

        $kas->payersQuery()->whereIn('users.id', $demoUsers)->get()->each(function (User $user) use ($kas, $bendahara, $current): void {
            // Variasi: ada yang lunas, ada yang menunggak beberapa minggu.
            $weeks = $kas->nextUnpaidWeeks($user, max(0, $current - random_int(0, 3)));

            if ($weeks !== []) {
                $kas->recordWeeks($bendahara, $user, $weeks);
            }
        });

        $this->command?->info('Data kas demo dibuat untuk akun @demo.test.');
    }
}
