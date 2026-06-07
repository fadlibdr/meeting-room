<div class="py-2" x-data="{ copied: false }">
    @if($rotated)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--amber-200); background: var(--amber-50);">
            <span style="color: var(--amber-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--amber-800);">{{ __('URL langganan baru dibuat. URL lama tidak berlaku lagi — perbarui di aplikasi kalender Anda.') }}</span>
        </div>
    @endif

    <div class="card bpjs-rise">
        <div class="card--pad space-y-4">
            <p class="text-sm text-slate-600">
                {{ __('Salin URL ini lalu tambahkan sebagai "kalender langganan" (subscribe by URL) di aplikasi kalender Anda. Reservasi Anda akan muncul otomatis dan diperbarui berkala.') }}
            </p>

            <div class="flex items-stretch gap-2">
                <input type="text" readonly value="{{ $feedUrl }}"
                       x-ref="url"
                       class="input mono" style="font-size: 12.5px;" />
                <x-bpjs.button type="button"
                               x-on:click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-show="!copied">{{ __('Salin') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Tersalin!') }}</span>
                </x-bpjs.button>
            </div>

            <div class="text-xs text-slate-500 space-y-1">
                <p><strong>Outlook:</strong> {{ __('Tambah kalender → Berlangganan dari web → tempel URL.') }}</p>
                <p><strong>Google Calendar:</strong> {{ __('Kalender lain → Dari URL → tempel URL.') }}</p>
                <p><strong>Apple Calendar:</strong> {{ __('File → Langganan Kalender Baru → tempel URL.') }}</p>
            </div>
        </div>

        <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
            <span class="text-xs text-slate-400 mr-auto">{{ __('Jaga kerahasiaan URL ini — siapa pun yang memilikinya dapat melihat jadwal Anda.') }}</span>
            <x-bpjs.button variant="ghost" type="button" wire:click="regenerate"
                           wire:confirm="{{ __('Buat URL baru? URL lama akan berhenti bekerja.') }}">
                {{ __('Buat Ulang URL') }}
            </x-bpjs.button>
        </div>
    </div>
</div>
