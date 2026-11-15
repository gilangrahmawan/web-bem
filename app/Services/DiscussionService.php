<?php

namespace App\Services;

use App\Models\Discussion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Penjaga data pembahasan dari form. `docs_link` hanya dari pemegang discussion.set_docs_link:
 * dari role lain field dinonaktifkan di form DAN nilainya diabaikan di sini (AGENTS.md D4).
 */
class DiscussionService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(User $actor, array $data, ?Discussion $existing = null): array
    {
        if (Gate::forUser($actor)->denies('setDocsLink', Discussion::class)) {
            // Abaikan apa pun yang dikirim; pertahankan nilai lama saat edit.
            $data['docs_link'] = $existing?->docs_link;
        } elseif (filled($data['docs_link'] ?? null) && ! preg_match('#^https?://\S+$#i', trim((string) $data['docs_link']))) {
            $data['docs_link'] = $existing?->docs_link;
        }

        $data['reference_links'] = collect($data['reference_links'] ?? [])
            // Hanya http/https (cegah javascript: dsb. walau form dimanipulasi).
            ->filter(fn ($row) => is_array($row) && preg_match('#^https?://\S+$#i', trim((string) ($row['url'] ?? ''))))
            ->map(fn (array $row) => [
                'url' => trim((string) $row['url']),
                'label' => filled($row['label'] ?? null) ? trim((string) $row['label']) : null,
            ])
            ->values()
            ->all();

        if ($existing === null) {
            $data['created_by'] = $actor->getKey();
        } else {
            unset($data['created_by'], $data['aspiration_id']);
        }

        return $data;
    }

    public function logSaved(User $actor, Discussion $discussion, bool $created): void
    {
        $this->logger->log($created ? 'discussion.created' : 'discussion.updated', $discussion, [
            'title' => $discussion->title,
            'status' => $discussion->status->value,
        ], $actor);
    }
}
