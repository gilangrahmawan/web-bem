<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pesan chat. body TERENKRIPSI di database (cast encrypted, memakai APP_KEY):
 * APP_KEY produksi wajib dibackup dan tidak boleh di-generate ulang, atau pesan lama tidak terbaca.
 * Pesan dihapus = soft delete + body & lampiran dikosongkan ("Pesan dihapus").
 */
class Message extends Model
{
    use SoftDeletes;

    public const DISK = 'local';

    public const DIRECTORY = 'chat';

    /** @var list<string> */
    protected $fillable = [
        'conversation_id',
        'body',
        'reply_to_id',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'attachment_size' => 'integer',
            'attachment_expired_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /** Ringkasan aman untuk daftar percakapan/notifikasi (tanpa HTML). */
    public function preview(int $limit = 80): string
    {
        if ($this->trashed()) {
            return 'Pesan dihapus';
        }

        $text = trim((string) $this->body);

        if ($text === '') {
            return match (true) {
                $this->attachment_expired_at !== null => 'Lampiran sudah kedaluwarsa',
                $this->isImage() => '📷 Gambar',
                $this->hasAttachment() => '📎 '.$this->attachment_name,
                default => '',
            };
        }

        return mb_strimwidth(preg_replace('/\s+/', ' ', $text) ?? $text, 0, $limit, '…');
    }
}
