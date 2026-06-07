<div class="py-2" x-data="{ copied: false }">
    @if(session('status'))
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ session('status') }}</span>
        </div>
    @endif
    @error('calendar')
        <div class="card card--pad bpjs-rise mb-4 text-sm font-medium" style="border-color: var(--red-200); background: var(--red-50); color: var(--red-700);">{{ $message }}</div>
    @enderror

    @if(!empty($twoWayProviders))
        <div class="card bpjs-rise mb-5">
            <div class="card--pad space-y-3">
                <h3 class="text-sm font-semibold text-slate-800">{{ __('Sinkronisasi Dua Arah') }}</h3>
                <p class="text-sm text-slate-600">{{ __('Hubungkan kalender Anda agar reservasi yang disetujui otomatis muncul (dan diperbarui/dibatalkan) sebagai acara di kalender Anda.') }}</p>
                <div class="space-y-2">
                    @foreach($twoWayProviders as $p)
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-semibold text-slate-700">{{ $p['label'] }}</span>
                                @if($p['connected'])
                                    <x-bpjs.pill variant="green">{{ __('Terhubung') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ __('Belum terhubung') }}</x-bpjs.pill>
                                @endif
                            </div>
                            @if($p['connected'])
                                <form method="POST" action="{{ route('calendar.disconnect', ['provider' => $p['key']]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Putuskan') }}</button>
                                </form>
                            @else
                                <a href="{{ route('calendar.connect', ['provider' => $p['key']]) }}"
                                   class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Hubungkan') }}</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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
