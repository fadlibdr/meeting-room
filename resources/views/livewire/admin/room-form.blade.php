<div>
    <form wire:submit="save">
        <div class="card bpjs-rise">
            <div class="card--pad space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field label="Kode Ruang" req for="code" :error="$errors->first('code')">
                        <input wire:model="code" type="text" id="code" placeholder="RM-A01"
                               class="input @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Nama Ruang" req for="name" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="name" placeholder="Ruang Garuda 1"
                               class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Lokasi / Gedung" for="location" :error="$errors->first('location')">
                        <input wire:model="location" type="text" id="location"
                               class="input @error('location') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Lantai" for="floor" :error="$errors->first('floor')">
                        <input wire:model="floor" type="text" id="floor" placeholder="Lantai 3"
                               class="input @error('floor') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Kapasitas" req for="capacity" :error="$errors->first('capacity')">
                        <input wire:model="capacity" type="number" min="1" id="capacity"
                               class="input @error('capacity') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Buffer Setelah Rapat (menit)" for="bookingBufferMinutes" :error="$errors->first('bookingBufferMinutes')">
                        <input wire:model="bookingBufferMinutes" type="number" min="0" id="bookingBufferMinutes"
                               class="input @error('bookingBufferMinutes') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Status" req for="status" :error="$errors->first('status')">
                        <select wire:model="status" id="status" class="select @error('status') input--err @enderror">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field label="Mode Approval" req for="approvalMode"
                                  :error="$errors->first('approvalMode')"
                                  hint="Mengubah mode tidak memengaruhi booking yang sedang berjalan (snapshot saat submit).">
                        <select wire:model="approvalMode" id="approvalMode" class="select @error('approvalMode') input--err @enderror">
                            @foreach($approvalModes as $m)
                                <option value="{{ $m->value }}">{{ $m->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>
                </div>

                <x-bpjs.field label="Deskripsi" for="description" :error="$errors->first('description')">
                    <textarea wire:model="description" id="description" rows="3"
                              class="textarea @error('description') input--err @enderror"></textarea>
                </x-bpjs.field>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" :href="route('admin.rooms.index')" wire:navigate>Batal</x-bpjs.button>
                <x-bpjs.button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Simpan Perubahan' : 'Buat Ruang' }}</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </x-bpjs.button>
            </div>
        </div>
    </form>
</div>
