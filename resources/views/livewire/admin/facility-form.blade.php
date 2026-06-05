<div>
    <form wire:submit="save">
        <div class="card bpjs-rise">
            <div class="card--pad space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field label="Kode Fasilitas" req for="code" :error="$errors->first('code')">
                        <input wire:model="code" type="text" id="code" placeholder="projector"
                               class="input @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Nama Fasilitas" req for="name" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="name" placeholder="Proyektor"
                               class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field label="Kategori" for="category" :error="$errors->first('category')">
                        <select wire:model="category" id="category" class="select @error('category') input--err @enderror">
                            <option value="">— Tanpa kategori —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field label="Ikon (opsional)" for="icon" :error="$errors->first('icon')">
                        <input wire:model="icon" type="text" id="icon" placeholder="nama-ikon"
                               class="input @error('icon') input--err @enderror" />
                    </x-bpjs.field>
                </div>

                <label class="flex items-center cursor-pointer gap-2">
                    <input type="checkbox" wire:model="isActive"
                           class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                    <span class="text-sm text-slate-700">Fasilitas aktif</span>
                </label>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" :href="route('admin.facilities.index')" wire:navigate>Batal</x-bpjs.button>
                <x-bpjs.button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Simpan Perubahan' : 'Buat Fasilitas' }}</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </x-bpjs.button>
            </div>
        </div>
    </form>
</div>
