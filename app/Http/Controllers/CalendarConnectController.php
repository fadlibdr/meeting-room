<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Stage 3 F.2 (activation) — per-user OAuth "connect calendar" flow for the
 * delegated two-way sync. Stores an encrypted CalendarConnection so
 * CalendarSyncService can write to the user's own Outlook/Google calendar.
 */
class CalendarConnectController extends Controller
{
    /** App provider key => Socialite driver name. */
    private const DRIVERS = [
        'microsoft' => 'azure',
        'google' => 'google',
    ];

    /** App provider key => OAuth scopes requested for calendar write. */
    private const SCOPES = [
        'microsoft' => ['offline_access', 'https://graph.microsoft.com/Calendars.ReadWrite'],
        'google' => ['https://www.googleapis.com/auth/calendar.events'],
    ];

    public function redirect(string $provider): SymfonyRedirect
    {
        $this->guard($provider);

        $driver = Socialite::driver(self::DRIVERS[$provider]);

        if ($driver instanceof AbstractProvider) {
            $driver->scopes(self::SCOPES[$provider])->redirectUrl($this->callbackUrl($provider));

            if ($provider === 'google') {
                // Force a refresh token on Google (only returned with offline + consent).
                $driver->with(['access_type' => 'offline', 'prompt' => 'consent']);
            }
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->guard($provider);

        try {
            $driver = Socialite::driver(self::DRIVERS[$provider]);
            if ($driver instanceof AbstractProvider) {
                $driver->redirectUrl($this->callbackUrl($provider));
            }
            $identity = $driver->user();
        } catch (Throwable $e) {
            Log::warning('Calendar connect failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return redirect()->route('calendar-subscription.index')->withErrors([
                'calendar' => __('Gagal menghubungkan kalender. Silakan coba lagi.'),
            ]);
        }

        if (! $identity instanceof SocialiteUser) {
            return redirect()->route('calendar-subscription.index')->withErrors([
                'calendar' => __('Gagal menghubungkan kalender. Silakan coba lagi.'),
            ]);
        }

        $expiresIn = $identity->expiresIn;

        CalendarConnection::query()->updateOrCreate(
            ['user_id' => $this->user()->id, 'provider' => $provider],
            [
                'access_token' => $identity->token,
                'refresh_token' => $identity->refreshToken,
                'token_expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds($expiresIn) : null,
                'is_active' => true,
            ],
        );

        return redirect()->route('calendar-subscription.index')
            ->with('status', __('Kalender berhasil dihubungkan.'));
    }

    public function disconnect(string $provider): RedirectResponse
    {
        $this->guard($provider);

        CalendarConnection::query()
            ->where('user_id', $this->user()->id)
            ->where('provider', $provider)
            ->delete();

        return redirect()->route('calendar-subscription.index')
            ->with('status', __('Kalender diputuskan.'));
    }

    private function guard(string $provider): void
    {
        abort_unless(isset(self::DRIVERS[$provider]), 404);
        abort_unless((bool) config('calendar.sync.enabled'), 404);
        abort_unless((bool) config("calendar.{$provider}.enabled"), 404);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function callbackUrl(string $provider): string
    {
        return route('calendar.connect.callback', ['provider' => $provider]);
    }
}
