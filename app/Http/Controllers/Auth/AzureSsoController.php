<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sso\SsoUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Stage 3 F.1 — Microsoft Entra ID (Azure AD) OIDC login.
 *
 * Both endpoints 404 unless SSO is enabled (config/sso.php). The callback
 * provisions/links the user via {@see SsoUserProvisioner}, then logs them in.
 */
class AzureSsoController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless((bool) config('sso.enabled'), 404);

        return Socialite::driver('azure')->redirect();
    }

    public function callback(SsoUserProvisioner $provisioner): RedirectResponse
    {
        abort_unless((bool) config('sso.enabled'), 404);

        try {
            $identity = Socialite::driver('azure')->user();
        } catch (Throwable $e) {
            Log::warning('SSO callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => __('Login SSO gagal. Silakan coba lagi atau gunakan kata sandi.'),
            ]);
        }

        try {
            $user = $provisioner->provision($identity);
        } catch (Throwable $e) {
            Log::warning('SSO provisioning failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => __('Akun SSO Anda tidak dapat digunakan untuk masuk.'),
            ]);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'email' => __('Akun Anda tidak aktif. Hubungi administrator.'),
            ]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
