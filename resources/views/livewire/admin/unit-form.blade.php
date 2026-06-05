<div>
    <form wire:submit="save">
        <div class="card bpjs-rise">
            <div class="card--pad space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field label="Kode Unit" req for="code" :error="$errors->first('code')">
                        <input wire:model="code" type="text" id="code" placeholder="BIRO-UMUM"
                               class="input @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Nama Unit" req for="name" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="name" placeholder="Biro Umum"
                               class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Unit Induk" for="parentId"
                        hint="Opsional — untuk struktur berjenjang."
                        :error="$errors->first('parentId')">
                        <select wire:model="parentId" id="parentId" class="select @error('parentId') input--err @enderror">
                            <option value="">— Tanpa induk (unit puncak) —</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }} ({{ $parent->code }})</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>
                </div>

                <label class="flex items-center cursor-pointer gap-2">
                    <input type="checkbox" wire:model="isActive"
                           class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                    <span class="text-sm text-slate-700">Unit aktif</span>
                </label>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" :href="route('admin.units.index')" wire:navigate>Batal</x-bpjs.button>
                <x-bpjs.button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Simpan Perubahan' : 'Buat Unit' }}</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </x-bpjs.button>
            </div>
        </div>
    </form>
</div>
