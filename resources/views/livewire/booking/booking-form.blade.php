<div>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            {{-- Page Header --}}
            <div class="mb-8">
                <h1 class="font-display text-3xl font-semibold text-slate-900 tracking-tight">
                    Buat Reservasi Ruangan
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Pilih ruangan, waktu, dan detail rapat. Sistem akan memeriksa
                    ketersediaan secara otomatis.
                </p>
            </div>

            {{-- Form-level error banner (M1-B-Dec-4: top placement) --}}
            @if ($submitError)
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-display text-sm font-semibold text-red-800">
                                Reservasi gagal disimpan
                            </h3>
                            <p class="mt-1 text-sm text-red-700">
                                {{ $submitError }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <form wire:submit="submit" class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">

                {{-- Section: Room --}}
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="font-display text-base font-semibold text-slate-900 mb-4">
                        Ruangan
                    </h2>
                    <div>
                        <label for="roomId" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Pilih Ruangan <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <select
                            wire:model="roomId"
                            id="roomId"
                            class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                        >
                            <option value="">— Pilih Ruangan —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">
                                    {{ $room->name }}@if ($room->floor || $room->location) ({{ trim(($room->floor ?? '').($room->floor && $room->location ? ', ' : '').($room->location ?? '')) }})@endif — Kapasitas {{ $room->capacity }}
                                </option>
                            @endforeach
                        </select>
                        @error('roomId')
                            <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-slate-500">
                            Picker visual akan menggantikan dropdown ini di M1-F.
                        </p>
                    </div>
                </div>

                {{-- Section: Time --}}
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="font-display text-base font-semibold text-slate-900 mb-4">
                        Waktu
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="startsAt" class="block text-sm font-medium text-slate-700 mb-1.5">
                                Waktu Mulai <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                wire:model="startsAt"
                                type="datetime-local"
                                id="startsAt"
                                class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                            />
                            @error('startsAt')
                                <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="endsAt" class="block text-sm font-medium text-slate-700 mb-1.5">
                                Waktu Selesai <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                wire:model="endsAt"
                                type="datetime-local"
                                id="endsAt"
                                class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                            />
                            @error('endsAt')
                                <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Pemeriksaan ketersediaan langsung akan ditambahkan di M1-C.
                    </p>
                </div>

                {{-- Section: Details --}}
                <div class="px-6 py-5">
                    <h2 class="font-display text-base font-semibold text-slate-900 mb-4">
                        Detail Rapat
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-slate-700 mb-1.5">
                                Judul Rapat <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                wire:model="subject"
                                type="text"
                                id="subject"
                                maxlength="150"
                                placeholder="Misal: Rapat Mingguan Tim IT"
                                class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                            />
                            @error('subject')
                                <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="agenda" class="block text-sm font-medium text-slate-700 mb-1.5">
                                Agenda
                                <span class="text-slate-400 font-normal text-xs">(opsional)</span>
                            </label>
                            <textarea
                                wire:model="agenda"
                                id="agenda"
                                rows="4"
                                maxlength="5000"
                                placeholder="Tujuan dan poin-poin yang akan dibahas dalam rapat."
                                class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                            ></textarea>
                            @error('agenda')
                                <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="max-w-xs">
                            <label for="attendeeCount" class="block text-sm font-medium text-slate-700 mb-1.5">
                                Jumlah Peserta <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                wire:model="attendeeCount"
                                type="number"
                                id="attendeeCount"
                                min="1"
                                class="block w-full rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                            />
                            @error('attendeeCount')
                                <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Footer: Actions --}}
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <a href="{{ route('calendar.index') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors">
                        Batal
                    </a>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="inline-flex items-center px-5 py-2 bg-bpjs-blue-500 hover:bg-bpjs-blue-600 disabled:bg-bpjs-blue-300 text-white text-sm font-medium rounded-md shadow-sm transition-colors disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bpjs-blue-500"
                    >
                        <span wire:loading.remove wire:target="submit">
                            Buat Reservasi
                        </span>
                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
