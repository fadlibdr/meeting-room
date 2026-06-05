<div>
    <form wire:submit="save" class="card bpjs-rise" style="overflow: hidden;">
        <div class="card--pad" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="r-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <x-bpjs.field label="Ruangan" req for="roomId" :error="$errors->first('roomId')">
                    <select wire:model.live="roomId" id="roomId" class="select @error('roomId') input--err @enderror">
                        <option value="">— Pilih ruang —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->name }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>

                <x-bpjs.field label="Jenis Blokir" req for="blockType" :error="$errors->first('blockType')">
                    <select wire:model="blockType" id="blockType" class="select @error('blockType') input--err @enderror">
                        @foreach($blockTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
            </div>

            <x-bpjs.field label="Judul" req for="title" :error="$errors->first('title')">
                <input wire:model="title" type="text" id="title" placeholder="contoh: Pemeliharaan AC"
                       class="input @error('title') input--err @enderror" />
            </x-bpjs.field>

            <div class="r-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <x-bpjs.field label="Mulai" req for="startsAt" :error="$errors->first('startsAt')">
                    <input wire:model.live="startsAt" type="datetime-local" id="startsAt"
                           class="input @error('startsAt') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field label="Selesai" req for="endsAt" :error="$errors->first('endsAt')">
                    <input wire:model.live="endsAt" type="datetime-local" id="endsAt"
                           class="input @error('endsAt') input--err @enderror" />
                </x-bpjs.field>
            </div>

            <x-bpjs.field label="Alasan (opsional)" for="reason" :error="$errors->first('reason')">
                <textarea wire:model="reason" id="reason" rows="2" class="textarea @error('reason') input--err @enderror"></textarea>
            </x-bpjs.field>

            @if($conflicts->isNotEmpty())
                <div class="card" style="padding: 16px; border-color: var(--amber-200); background: var(--amber-50);">
                    <p class="text-sm font-semibold" style="color: var(--amber-800);">
                        Booking yang bentrok ({{ $conflicts->count() }}):
                    </p>
                    <ul class="mt-2 space-y-1 text-sm" style="color: var(--amber-700);">
                        @foreach($conflicts as $b)
                            <li>• {{ $b->subject }} (<span class="mono">{{ $b->starts_at->format('d M H:i') }}–{{ $b->ends_at->format('H:i') }}</span>)</li>
                        @endforeach
                    </ul>
                    <label class="mt-3 flex items-center cursor-pointer gap-2">
                        <input type="checkbox" wire:model="cancelConflicting"
                               style="accent-color: var(--red-600); width: 16px; height: 16px;" />
                        <span class="text-sm" style="color: var(--amber-800);">Batalkan booking di atas dan tetap buat blokir</span>
                    </label>
                </div>
            @endif

            @error('conflict') <p class="field__err">{{ $message }}</p> @enderror
        </div>

        <div class="modal__foot">
            <x-bpjs.button variant="ghost" :href="route('admin.room-blocks.index')" wire:navigate>Batal</x-bpjs.button>
            <x-bpjs.button variant="primary" icon="check" type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Buat Blokir</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </x-bpjs.button>
        </div>
    </form>
</div>
