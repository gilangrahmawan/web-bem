<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Percakapan chat: personal, grup divisi/organisasi (otomatis), atau grup buatan pengguna. */
class Conversation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'type',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot(['role', 'last_read_at', 'muted'])->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function membership(User $user): ?ConversationMember
    {
        return $this->members()->where('user_id', $user->getKey())->first();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->getKey())->exists();
    }

    /** Lawan bicara di percakapan personal. */
    public function otherUser(User $me): ?User
    {
        return $this->users()->where('users.id', '!=', $me->getKey())->first();
    }

    public function displayName(User $me): string
    {
        if ($this->type === ConversationType::Personal) {
            return $this->otherUser($me)?->name ?? 'Percakapan';
        }

        return (string) $this->name;
    }

    /** @param  Builder<Conversation>  $query */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $user->getKey()));
    }

    public static function personalKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }
}
