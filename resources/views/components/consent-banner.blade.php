{{--
    Stage 4f.4 — cookie/consent banner.

    Privacy-preserving default: analytics/non-essential stays OFF until the user
    presses "Terima Semua". The choice is stored in the plaintext `cookie_consent`
    cookie (exempt from encryption) so the server can gate non-essential scripts.

    Shown only until a choice is made (server-side @unless avoids a flash; Alpine
    also re-checks client-side after a JS-set choice without a reload).
--}}
@unless(\App\Support\Consent::decided())
    <div x-data="{
            show: true,
            choose(value) {
                document.cookie = 'cookie_consent=' + value + ';path=/;max-age=' + (60*60*24*365) + ';SameSite=Lax';
                this.show = false;
                if (value === 'all') { window.location.reload(); }
            }
         }"
         x-show="show"
         x-cloak
         role="dialog"
         aria-label="{{ __('Persetujuan cookie') }}"
         class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white shadow-lg">
        <div class="mx-auto flex max-w-4xl flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">
                {{ __('Kami menggunakan cookie esensial agar layanan berfungsi. Cookie analitik (non-esensial) hanya aktif jika Anda menyetujui.') }}
                <a href="{{ route('legal.show', 'privacy') }}" class="font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Selengkapnya') }}</a>
            </p>
            <div class="flex shrink-0 gap-2">
                <button type="button" @click="choose('essential')" class="btn btn--ghost">
                    {{ __('Hanya Esensial') }}
                </button>
                <button type="button" @click="choose('all')" class="btn btn--primary">
                    {{ __('Terima Semua') }}
                </button>
            </div>
        </div>
    </div>
@endunless
