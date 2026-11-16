<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Perawatan chat harian: sinkron grup otomatis (jaring pengaman) + hapus lampiran yang lebih tua dari
 * chat_attachment_retention_months (default 12 bulan; teks pesan tetap, tampil "Lampiran sudah kedaluwarsa").
 */
#[Signature('chat:maintenance')]
#[Description('Sinkron grup chat otomatis dan hapus lampiran chat yang kedaluwarsa')]
class ChatMaintenance extends Command
{
    public function handle(ConversationSyncService $sync, ChatService $chat): int
    {
        $changes = $sync->syncAll();
        $purged = $chat->purgeExpiredAttachments();

        $this->info("Grup otomatis: {$changes} perubahan anggota. Lampiran kedaluwarsa dihapus: {$purged}.");

        return self::SUCCESS;
    }
}
