<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the UI language. Always remembered in the session; for an
     * authenticated user it is also persisted to their profile so the choice
     * survives across devices. Unknown locales are ignored.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        /** @var array<string, string> $available */
        $available = config('app.available_locales', []);

        if (array_key_exists($locale, $available)) {
            $request->session()->put('locale', $locale);

            $user = $request->user();
            if ($user !== null) {
                $user->forceFill(['locale' => $locale])->save();
            }
        }

        return redirect()->back();
    }
}
