<div>
    <a href="{{ $bookingId ? route('bookings.show', $bookingId) : route('calendar.index') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors mb-4">
        <x-icon name="chevronLeft" :size="15" /> {{ __('Batal') }}
    </a>

    @if ($mode === 'reschedule')
        <div class="card card--pad bpjs-rise mb-5" style="border-color: var(--bpjs-blue-200); background: var(--bpjs-blue-50);">
            <p class="text-sm text-bpjs-blue-800">
                {!! __('Reservasi lama akan <strong>dibatalkan</strong> dan digantikan dengan reservasi baru sesuai jadwal yang Anda tentukan di bawah.') !!}
            </p>
        </div>
    @endif

    {{-- Form-level error banner (M1-B-Dec-4: top placement) --}}
    @if ($submitError)
        <div class="card card--pad bpjs-rise mb-5" role="alert" style="border-color: var(--red-300); background: var(--red-50);">
            <div class="flex items-start gap-3">
                <span class="text-red-600 mt-0.5 flex-shrink-0"><x-icon name="alert" :size="20" /></span>
                <div>
                    <h3 class="font-display text-sm font-semibold text-red-800">
                        {{ __('Reservasi gagal disimpan') }}
                    </h3>
                    <p class="mt-1 text-sm text-red-700">
                        {{ $submitError }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="submit">
        <x-bpjs.card rise>
            {{-- Stepper --}}
            <div class="flex items-center mb-6">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-bold font-display text-white" style="background: var(--bpjs-blue-600);">1</span>
                    <span class="text-[13px] font-semibold text-slate-900">{{ __('Detail Rapat') }}</span>
                </div>
                <div class="h-0.5 mx-3.5" style="width: 48px; background: var(--bpjs-blue-400);"></div>
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-bold font-display text-white" style="background: var(--bpjs-blue-600);">2</span>
                    <span class="text-[13px] font-semibold text-slate-900">{{ __('Pilih Ruangan') }}</span>
                </div>
            </div>

            {{-- Step 1: Detail Rapat --}}
            <div class="flex flex-col gap-[18px]">
                <x-bpjs.field :label="__('Judul Rapat')" req for="subject" :error="$errors->first('subject')">
                    <input
                        wire:model="subject"
                        type="text"
                        id="subject"
                        maxlength="150"
                        placeholder="{{ __('cth. Rapat Mingguan Tim IT') }}"
                        class="input @error('subject') input--err @enderror"
                    />
                </x-bpjs.field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-[14px] r-form-3">
                    <x-bpjs.field :label="__('Waktu Mulai')" req for="startsAt" :error="$errors->first('startsAt')">
                        <input
                            wire:model.live.debounce.500ms="startsAt"
                            type="datetime-local" lang="id"
                            id="startsAt"
                            class="input @error('startsAt') input--err @enderror"
                        />
                    </x-bpjs.field>
                    <x-bpjs.field :label="__('Waktu Selesai')" req for="endsAt" :error="$errors->first('endsAt')">
                        <input
                            wire:model.live.debounce.500ms="endsAt"
                            type="datetime-local" lang="id"
                            id="endsAt"
                            class="input @error('endsAt') input--err @enderror"
                        />
                    </x-bpjs.field>
                </div>

                <x-bpjs.field :label="__('Jumlah Peserta')" req for="attendeeCount" :hint="__('Digunakan untuk mengecek kapasitas ruangan.')" :error="$errors->first('attendeeCount')">
                    <input
                        wire:model="attendeeCount"
                        type="number"
                        id="attendeeCount"
                        min="1"
                        class="input @error('attendeeCount') input--err @enderror"
                        style="max-width: 200px;"
                    />
                </x-bpjs.field>

                <x-bpjs.field :label="__('Catatan')" :hint="__('Opsional')" for="agenda" :error="$errors->first('agenda')">
                    <textarea
                        wire:model="agenda"
                        id="agenda"
                        rows="3"
                        maxlength="5000"
                        placeholder="{{ __('Poin-poin yang akan dibahas dalam rapat…') }}"
                        class="textarea @error('agenda') input--err @enderror"
                    ></textarea>
                </x-bpjs.field>

                {{-- Recurrence (create mode only) --}}
                @if ($mode === 'create')
                    <div class="pt-5" style="border-top: 1px solid var(--slate-100);">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="recurring"
                                   class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                            <span class="text-sm font-semibold text-slate-800 inline-flex items-center gap-1.5">
                                <x-icon name="calendar" :size="16" /> {{ __('Ulangi reservasi (berulang)') }}
                            </span>
                        </label>

                        @if ($recurring)
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5 rounded-[12px] p-4"
                                 style="background: var(--slate-50); border: 1px solid var(--slate-200);">
                                <x-bpjs.field :label="__('Frekuensi')" for="recurrenceFrequency" :error="$errors->first('recurrenceFrequency')">
                                    <select wire:model.live="recurrenceFrequency" id="recurrenceFrequency" class="select">
                                        <option value="daily">{{ __('Harian') }}</option>
                                        <option value="weekly">{{ __('Mingguan') }}</option>
                                        <option value="monthly">{{ __('Bulanan') }}</option>
                                    </select>
                                </x-bpjs.field>

                                <x-bpjs.field :label="__('Setiap')" :hint="__('mis. setiap 2 minggu')" for="recurrenceInterval" :error="$errors->first('recurrenceInterval')">
                                    <input type="number" wire:model="recurrenceInterval" id="recurrenceInterval" min="1" max="12"
                                           class="input @error('recurrenceInterval') input--err @enderror" />
                                </x-bpjs.field>

                                <x-bpjs.field :label="__('Berakhir')" for="recurrenceEnd" :error="$errors->first('recurrenceEnd')">
                                    <select wire:model.live="recurrenceEnd" id="recurrenceEnd" class="select">
                                        <option value="count">{{ __('Setelah sejumlah kejadian') }}</option>
                                        <option value="until">{{ __('Sampai tanggal') }}</option>
                                    </select>
                                </x-bpjs.field>

                                @if ($recurrenceEnd === 'count')
                                    <x-bpjs.field :label="__('Jumlah kejadian')" for="recurrenceCount" :error="$errors->first('recurrenceCount')">
                                        <input type="number" wire:model="recurrenceCount" id="recurrenceCount" min="1" max="100"
                                               class="input @error('recurrenceCount') input--err @enderror" />
                                    </x-bpjs.field>
                                @else
                                    <x-bpjs.field :label="__('Sampai tanggal')" for="recurrenceUntil" :error="$errors->first('recurrenceUntil')">
                                        <input type="date" wire:model="recurrenceUntil" id="recurrenceUntil"
                                               class="input @error('recurrenceUntil') input--err @enderror" />
                                    </x-bpjs.field>
                                @endif

                                <p class="md:col-span-2 field__hint">
                                    {{ __('Jadwal yang bentrok akan dilewati otomatis; sisanya tetap dibuat. Setiap jadwal tetap melalui alur persetujuan seperti biasa.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Live availability banner (M1-C) --}}
            @if ($conflictStatus !== 'unknown')
                <div class="mt-4">
                    @if ($conflictStatus === 'checking')
                        <div class="flex items-center gap-2 rounded-[10px] px-3.5 py-2.5 text-sm text-slate-600" style="background: var(--slate-50); border: 1px solid var(--slate-200);">
                            <svg class="h-4 w-4 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('Memeriksa ketersediaan') }}&hellip;
                        </div>
                    @elseif ($conflictStatus === 'clear')
                        <div class="flex items-start gap-2 rounded-[10px] px-3.5 py-2.5 text-sm text-bpjs-green-800" style="background: var(--bpjs-green-50); border: 1px solid var(--bpjs-green-200);">
                            <span class="text-bpjs-green-600 flex-shrink-0"><x-icon name="checkCircle" :size="18" /></span>
                            <span>{{ __('Slot tersedia. Anda dapat melanjutkan reservasi.') }}</span>
                        </div>
                    @elseif ($conflictStatus === 'conflict')
                        <div class="rounded-[10px] px-3.5 py-2.5 text-sm text-amber-800" style="background: var(--amber-50); border: 1px solid var(--amber-200);">
                            <div class="flex items-start gap-2">
                                <span class="text-amber-700 flex-shrink-0 mt-0.5"><x-icon name="alert" :size="18" /></span>
                                <div class="flex-1">
                                    <p class="font-semibold text-amber-900">{{ __('Slot bentrok dengan jadwal lain') }}</p>
                                    <ul class="mt-1.5 space-y-1 text-xs text-amber-800">
                                        @foreach ($conflictDetails as $conflict)
                                            <li class="flex items-start gap-1.5">
                                                @if ($conflict['type'] === 'booking')
                                                    <x-bpjs.pill variant="amber">{{ __('Booking') }}</x-bpjs.pill>
                                                @elseif ($conflict['type'] === 'block')
                                                    <x-bpjs.pill variant="slate">{{ __('Blokir') }}</x-bpjs.pill>
                                                @else
                                                    <x-bpjs.pill variant="red">{{ __('Jam Operasional') }}</x-bpjs.pill>
                                                @endif
                                                <span class="flex-1">
                                                    {{ $conflict['title'] }}
                                                    <span class="text-amber-700">— @displayDateTime($conflict['starts_at']) s.d. @displayDateTime($conflict['ends_at'])</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-2 text-xs">{{ __('Silakan pilih waktu atau ruangan lain.') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Step 2: Pilih Sumber Daya --}}
            <div class="mt-6 pt-6" style="border-top: 1px solid var(--slate-100);">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="card__h">{{ __('Pilih Ruangan') }} <span style="color: var(--red-500);">*</span></h2>
                    <div style="min-width: 200px;">
                        <select wire:model.live="resourceType" class="select" aria-label="{{ __('Jenis sumber daya') }}">
                            @foreach(\App\Enums\ResourceType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <livewire:booking.room-availability-picker :starts-at="$startsAt" :ends-at="$endsAt" :attendee-count="$attendeeCount" :selected-room-id="(int) $roomId" :exclude-booking-id="$bookingId" :resource-type="$resourceType" wire:key="picker-{{ $resourceType }}" />

                @error('roomId')
                    <p class="field__err mt-3" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-2.5 mt-6 pt-5" style="border-top: 1px solid var(--slate-100);">
                <x-bpjs.button variant="ghost" :href="$bookingId ? route('bookings.show', $bookingId) : route('calendar.index')" wire:navigate>
                    {{ __('Batal') }}
                </x-bpjs.button>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    @disabled($conflictStatus === 'conflict')
                    class="btn btn--primary"
                >
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                        <x-icon name="check" :size="17" />
                        {{ match($mode) { 'edit' => __('Simpan Perubahan'), 'reschedule' => __('Jadwalkan Ulang'), default => __('Ajukan Reservasi') } }}
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Menyimpan...') }}
                    </span>
                </button>
            </div>
        </x-bpjs.card>
    </form>
</div>
