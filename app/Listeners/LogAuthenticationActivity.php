<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/** Mencatat login, gagal login, dan logout ke activity_logs. */
class LogAuthenticationActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->logger->log('auth.login', $event->user, [], $event->user);
    }

    public function onFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        // Hanya email yang dicatat (tanpa password), dibatasi panjangnya.
        $this->logger->log('auth.failed', $user, [
            'email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 191),
            'reason' => $user ? ($user->is_active ? 'credentials_or_access' : 'inactive') : 'unknown_email',
        ], $user);
    }

    public function onLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->log('auth.logout', $event->user, [], $event->user);
    }
}
