<div>
    <form wire:submit="save" class="card bpjs-rise" style="overflow: hidden;">
        <div class="card--pad" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="r-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <x-bpjs.field :label="__('Ruangan')" req for="roomId" :error="$errors->first('roomId')">
                    <select wire:model.live="roomId" id="roomId" class="select @error('roomId') input--err @enderror">
                        <option value="">{{ __('— Pilih ruang —') }}</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->name }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>

                <x-bpjs.field :label="__('Jenis Blokir')" req for="blockType" :error="$errors->first('blockType')">
                    <select wire:model="blockType" id="blockType" class="select @error('blockType') input--err @enderror">
                        @foreach($blockTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
            </div>

            <x-bpjs.field :label="__('Judul')" req for="title" :error="$errors->first('title')">
                <input wire:model="title" type="text" id="title" placeholder="{{ __('contoh: Pemeliharaan AC') }}"
                       class="input @error('title') input--err @enderror" />
            </x-bpjs.field>

            <div class="r-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <x-bpjs.field :label="__('Mulai')" req for="startsAt" :error="$errors->first('startsAt')">
                    <input wire:model.live="startsAt" type="datetime-local" lang="id" id="startsAt"
                           class="input @error('startsAt') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field :label="__('Selesai')" req for="endsAt" :error="$errors->first('endsAt')">
                    <input wire:model.live="endsAt" type="datetime-local" lang="id" id="endsAt"
                           class="input @error('endsAt') input--err @enderror" />
                </x-bpjs.field>
            </div>

            <x-bpjs.field :label="__('Alasan (opsional)')" for="reason" :error="$errors->first('reason')">
                <textarea wire:model="reason" id="reason" rows="2" class="textarea @error('reason') input--err @enderror"></textarea>
            </x-bpjs.field>

            {{-- Recurrence --}}
            <div class="pt-4" style="border-top: 1px solid var(--slate-100);">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" wire:model.live="recurring"
                           class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                    <span class="text-sm font-semibold text-slate-800 inline-flex items-center gap-1.5">
                        <x-icon name="clock" :size="16" /> {{ __('Ulangi blokir (berulang)') }}
                    </span>
                </label>

                @if($recurring)
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 rounded-[12px] p-4"
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
                        @if($recurrenceEnd === 'count')
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
                            {{ __('Tiap kemunculan dibuat sebagai blokir tersendiri. Yang bentrok dengan reservasi akan dilewati, kecuali Anda mengaktifkan "batalkan booking bentrok".') }}
                        </p>
                    </div>
                @endif
            </div>

            @if($conflicts->isNotEmpty())
                <div class="card" style="padding: 16px; border-color: var(--amber-200); background: var(--amber-50);">
                    <p class="text-sm font-semibold" style="color: var(--amber-800);">
                        {{ __('Booking yang bentrok (:count):', ['count' => $conflicts->count()]) }}
                    </p>
                    <ul class="mt-2 space-y-1 text-sm" style="color: var(--amber-700);">
                        @foreach($conflicts as $b)
                            <li>• {{ $b->subject }} (<span class="mono">{{ $b->starts_at->format('d M H:i') }}–{{ $b->ends_at->format('H:i') }}</span>)</li>
                        @endforeach
                    </ul>
                    <label class="mt-3 flex items-center cursor-pointer gap-2">
                        <input type="checkbox" wire:model="cancelConflicting"
                               style="accent-color: var(--red-600); width: 16px; height: 16px;" />
                        <span class="text-sm" style="color: var(--amber-800);">{{ __('Batalkan booking di atas dan tetap buat blokir') }}</span>
                    </label>
                </div>
            @endif

            @error('conflict') <p class="field__err">{{ $message }}</p> @enderror
        </div>

        <div class="modal__foot">
            <x-bpjs.button variant="ghost" :href="route('admin.room-blocks.index')" wire:navigate>{{ __('Batal') }}</x-bpjs.button>
            <x-bpjs.button variant="primary" icon="check" type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Buat Blokir') }}</span>
                <span wire:loading wire:target="save">{{ __('Memproses...') }}</span>
            </x-bpjs.button>
        </div>
    </form>
</div>
