<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Confirms an email change — the link is sent to the NEW address (signed +
 * unguessable token). On success the pending email becomes the account email
 * and is marked verified.
 */
class EmailChangeController extends Controller
{
    public function verify(string $token): RedirectResponse
    {
        $user = User::withoutGlobalScope('tenant')
            ->where('pending_email_token', $token)
            ->whereNotNull('pending_email')
            ->first();

        if (! $user instanceof User) {
            return redirect()->route(Auth::check() ? 'profile' : 'login')
                ->with('status', __('Tautan konfirmasi tidak valid atau sudah digunakan.'));
        }

        // The pending email could have been taken by someone else meanwhile.
        $taken = User::withoutGlobalScope('tenant')
            ->where('email', $user->pending_email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            $user->forceFill(['pending_email' => null, 'pending_email_token' => null])->save();

            return redirect()->route(Auth::check() ? 'profile' : 'login')
                ->with('status', __('Alamat email tersebut sudah dipakai akun lain.'));
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'pending_email_token' => null,
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route(Auth::check() ? 'profile' : 'login')
            ->with('status', __('Email berhasil diperbarui.'));
    }
}
