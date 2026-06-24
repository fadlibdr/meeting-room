<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records authentication events to the security audit trail (SOC 2 CC7.2 /
 * ISO 27001 A.8.15). Subscribed in AppServiceProvider. Auth::attempt() (used by
 * the login form) fires Login/Failed; the form fires Lockout; logout fires
 * Logout — so no change to the auth flow is needed.
 *
 * Honours the security.audit_logging_enabled kill switch via ActivityLogger.
 */
final class SecurityEventSubscriber
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        $actor = $event->user instanceof User ? $event->user : null;

        $this->logger->security('login', $actor, [
            'description' => 'Login berhasil.',
            'context' => ['guard' => $event->guard],
        ], $actor);
    }

    public function handleLogout(Logout $event): void
    {
        $actor = $event->user instanceof User ? $event->user : null;

        $this->logger->security('logout', $actor, [
            'description' => 'Logout.',
            'context' => ['guard' => $event->guard],
        ], $actor);
    }

    public function handleFailed(Failed $event): void
    {
        // Never log the password — only the attempted identifier.
        $email = $event->credentials['email'] ?? null;
        $actor = $event->user instanceof User ? $event->user : null;

        $this->logger->security('login.failed', $actor, [
            'description' => 'Login gagal.',
            'context' => ['guard' => $event->guard, 'email' => $email],
        ], $actor);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->logger->security('lockout', null, [
            'description' => 'Akun/IP terkunci sementara karena terlalu banyak percobaan gagal.',
            'context' => ['ip' => $event->request->ip()],
        ]);
    }
}
