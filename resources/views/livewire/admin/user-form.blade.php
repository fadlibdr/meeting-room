<div>
    {{-- Generated Password Banner (shown once after create) --}}
    @if($generatedPassword)
        <div class="card card--pad bpjs-rise" style="border-color: var(--amber-200); background: var(--amber-50);">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <span style="color: var(--amber-700); display: inline-flex; flex-shrink: 0; margin-top: 1px;"><x-icon name="info" :size="20" /></span>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold" style="color: var(--amber-800);">
                        Pengguna berhasil dibuat — catat password sementara
                    </h3>
                    <p class="mt-1 text-sm" style="color: var(--amber-700);">
                        Password ini hanya ditampilkan sekali. Bagikan ke pengguna melalui kanal aman.
                    </p>
                    <div class="mono mt-3 inline-block bg-white rounded-lg px-4 py-2 text-lg select-all"
                         style="border: 1px solid var(--amber-200); color: var(--slate-900);">
                        {{ $generatedPassword }}
                    </div>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <x-bpjs.button variant="primary" :href="route('admin.users.index')" wire:navigate>
                    Kembali ke daftar pengguna
                </x-bpjs.button>
                <x-bpjs.button variant="ghost" :href="route('admin.users.create')" wire:navigate>
                    Tambah pengguna lagi
                </x-bpjs.button>
            </div>
        </div>
    @endif

    {{-- Form (hidden if password just generated; that case shows banner only) --}}
    @if(!$generatedPassword)
        <form wire:submit="save" class="card bpjs-rise" style="overflow: hidden;">
            <div class="card--pad" style="display: flex; flex-direction: column; gap: 16px;">
                <x-bpjs.field label="Nama Lengkap" req for="name" :error="$errors->first('name')">
                    <input wire:model="name" type="text" id="name" class="input @error('name') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field label="Email" req for="email" :error="$errors->first('email')">
                    <input wire:model="email" type="email" id="email"
                           placeholder="contoh: nama.belakang@bpjs-kesehatan.go.id"
                           class="input @error('email') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field label="Unit" req for="unitId" :error="$errors->first('unitId')">
                    <select wire:model="unitId" id="unitId" class="select @error('unitId') input--err @enderror">
                        <option value="">— Pilih unit —</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>

                <x-bpjs.field label="Peran" req :error="$errors->first('roleIds')">
                    <div class="card" style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                        @foreach($roles as $role)
                            <label class="flex items-center cursor-pointer gap-2">
                                <input type="checkbox" wire:model="roleIds" value="{{ $role->id }}"
                                       style="accent-color: var(--bpjs-blue-600); width: 16px; height: 16px;" />
                                <span class="text-sm text-slate-700">{{ $role->name }}</span>
                                <span class="mono text-xs text-slate-400">({{ $role->code }})</span>
                            </label>
                        @endforeach
                    </div>
                </x-bpjs.field>

                <div>
                    <label class="flex items-center cursor-pointer gap-2">
                        <input type="checkbox" wire:model="isActive"
                               style="accent-color: var(--bpjs-blue-600); width: 16px; height: 16px;" />
                        <span class="text-sm text-slate-700">Akun aktif</span>
                    </label>
                    <p class="field__hint">Pengguna nonaktif tidak dapat login.</p>
                </div>
            </div>

            <div class="modal__foot">
                <x-bpjs.button variant="ghost" :href="route('admin.users.index')" wire:navigate>Batal</x-bpjs.button>
                <x-bpjs.button variant="primary" icon="check" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Simpan Perubahan' : 'Buat Pengguna' }}</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </x-bpjs.button>
            </div>
        </form>
    @endif
</div>
