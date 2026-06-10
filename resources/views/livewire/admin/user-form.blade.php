<div>
    {{-- Generated Password Banner (shown once after create) --}}
    @if($generatedPassword)
        <div class="card card--pad bpjs-rise" style="border-color: var(--amber-200); background: var(--amber-50);">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <span style="color: var(--amber-700); display: inline-flex; flex-shrink: 0; margin-top: 1px;"><x-icon name="info" :size="20" /></span>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold" style="color: var(--amber-800);">
                        {{ __('Pengguna berhasil dibuat — catat password sementara') }}
                    </h3>
                    <p class="mt-1 text-sm" style="color: var(--amber-700);">
                        {{ __('Password ini hanya ditampilkan sekali. Bagikan ke pengguna melalui kanal aman.') }}
                    </p>
                    <div class="mono mt-3 inline-block bg-white rounded-lg px-4 py-2 text-lg select-all"
                         style="border: 1px solid var(--amber-200); color: var(--slate-900);">
                        {{ $generatedPassword }}
                    </div>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <x-bpjs.button variant="primary" :href="route('admin.users.index')" wire:navigate>
                    {{ __('Kembali ke daftar pengguna') }}
                </x-bpjs.button>
                <x-bpjs.button variant="ghost" :href="route('admin.users.create')" wire:navigate>
                    {{ __('Tambah pengguna lagi') }}
                </x-bpjs.button>
            </div>
        </div>
    @endif

    {{-- Form (hidden if password just generated; that case shows banner only) --}}
    @if(!$generatedPassword)
        <form wire:submit="save" class="card bpjs-rise" style="overflow: hidden;">
            <div class="card--pad" style="display: flex; flex-direction: column; gap: 16px;">
                <x-bpjs.field :label="__('Nama Lengkap')" req for="name" :error="$errors->first('name')">
                    <input wire:model="name" type="text" id="name" class="input @error('name') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field :label="__('Email')" req for="email" :error="$errors->first('email')">
                    <input wire:model="email" type="email" id="email"
                           placeholder="{{ __('contoh: nama.belakang@bpjs-kesehatan.go.id') }}"
                           class="input @error('email') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field :label="__('Unit')" req for="unitId" :error="$errors->first('unitId')">
                    <select wire:model="unitId" id="unitId" class="select @error('unitId') input--err @enderror">
                        <option value="">{{ __('— Pilih unit —') }}</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>

                <x-bpjs.field :label="__('Approver')" for="approverUserId"
                    :hint="__('Penyetuju reservasi pengguna ini (untuk ruangan mode Unit Approver).')"
                    :error="$errors->first('approverUserId')">
                    <select wire:model="approverUserId" id="approverUserId" class="select @error('approverUserId') input--err @enderror">
                        <option value="">{{ __('— Tidak ada approver —') }}</option>
                        @foreach($approvers as $approver)
                            <option value="{{ $approver->id }}">{{ $approver->name }} ({{ $approver->email }})</option>
                        @endforeach
                    </select>
                    @if($approvers->isEmpty())
                        <p class="field__hint" style="color: var(--amber-700);">
                            {{ __('Belum ada pengguna dengan peran Unit Approver / GA Admin. Buat akun tersebut dahulu untuk dapat menugaskan approver.') }}
                        </p>
                    @endif
                </x-bpjs.field>

                <x-bpjs.field :label="__('Peran')" req :error="$errors->first('roleIds')">
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
                        <span class="text-sm text-slate-700">{{ __('Akun aktif') }}</span>
                    </label>
                    <p class="field__hint">{{ __('Pengguna nonaktif tidak dapat login.') }}</p>
                </div>
            </div>

            <div class="modal__foot">
                <x-bpjs.button variant="ghost" :href="route('admin.users.index')" wire:navigate>{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button variant="primary" icon="check" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? __('Simpan Perubahan') : __('Buat Pengguna') }}</span>
                    <span wire:loading wire:target="save">{{ __('Memproses...') }}</span>
                </x-bpjs.button>
            </div>
        </form>

        {{-- Admin password reset (edit mode) --}}
        @if($isEditMode)
            <x-bpjs.card :title="__('Reset Kata Sandi')" class="mt-5">
                @if($resetResult)
                    <div class="mb-3 rounded-lg border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800">
                        {{ __('Kata sandi baru (salin & sampaikan ke pengguna; hanya ditampilkan sekali):') }}
                        <span class="mono font-bold">{{ $resetResult }}</span>
                    </div>
                @endif
                <p class="mb-3 text-sm text-slate-500">
                    {{ __('Setel kata sandi baru untuk pengguna ini. Kosongkan untuk membuat kata sandi acak yang kuat.') }}
                </p>
                <div class="flex flex-wrap items-end gap-3">
                    <x-bpjs.field :label="__('Kata Sandi Baru (opsional)')" for="newPassword" :error="$errors->first('newPassword')">
                        <input wire:model="newPassword" id="newPassword" type="text" autocomplete="off"
                               class="input @error('newPassword') input--err @enderror" placeholder="{{ __('min. 8 karakter') }}" />
                    </x-bpjs.field>
                    <x-bpjs.button variant="ghost" type="button" wire:click="resetPassword"
                                   wire:confirm="{{ __('Reset kata sandi pengguna ini?') }}">{{ __('Reset Kata Sandi') }}</x-bpjs.button>
                </div>
            </x-bpjs.card>
        @endif
    @endif
</div>
