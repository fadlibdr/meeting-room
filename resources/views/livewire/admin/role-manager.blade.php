<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($showForm)
        {{-- ===== Create / edit form ===== --}}
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field :label="__('Nama Peran')" req for="rname" :error="$errors->first('name')">
                        <input wire:model="name" id="rname" type="text" class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Kode')" req for="rcode"
                                  :hint="__('Huruf kecil, angka, garis bawah. Tidak dapat diubah untuk peran sistem.')"
                                  :error="$errors->first('code')">
                        <input wire:model="code" id="rcode" type="text" placeholder="contoh_peran"
                               @disabled(! $creating && \App\Models\Role::find($editingId)?->is_system)
                               class="input font-mono @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Cakupan')" req for="rscope" :error="$errors->first('scope')">
                        <select wire:model="scope" id="rscope" class="select">
                            <option value="strategic">{{ __('Strategis') }}</option>
                            <option value="operational">{{ __('Operasional') }}</option>
                            <option value="support">{{ __('Pendukung') }}</option>
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Deskripsi')" for="rdesc" :error="$errors->first('description')">
                        <input wire:model="description" id="rdesc" type="text" class="input" />
                    </x-bpjs.field>
                </div>

                {{-- Permission matrix --}}
                <div>
                    <div class="eyebrow mb-2">{{ __('Matriks Izin') }}</div>
                    <div class="space-y-4">
                        @foreach($permissionsByModule as $module => $perms)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="mb-2 text-sm font-semibold text-slate-800">{{ ucfirst(str_replace('-', ' ', $module)) }}</div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-3 lg:grid-cols-4">
                                    @foreach($perms as $perm)
                                        <label class="flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" wire:click="togglePermission({{ $perm->id }})"
                                                   @checked(in_array($perm->id, $permissionIds))
                                                   class="rounded border-slate-300 text-bpjs-blue-600 focus:ring-bpjs-blue-500" />
                                            <span class="font-mono text-xs">{{ $perm->action }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="closeForm">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit">{{ __('Simpan') }}</x-bpjs.button>
            </div>
        </form>
    @else
        {{-- ===== Roles list ===== --}}
        @hasPermission('roles.create')
            <div class="mb-4 flex justify-end">
                <x-bpjs.button wire:click="newRole" icon="plus">{{ __('Tambah Peran') }}</x-bpjs.button>
            </div>
        @endhasPermission

        <x-bpjs.card :pad="false" class="overflow-hidden">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>{{ __('Peran') }}</th>
                        <th>{{ __('Kode') }}</th>
                        <th>{{ __('Pengguna') }}</th>
                        <th>{{ __('Izin') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="font-semibold text-slate-900">
                                {{ $role->name }}
                                @if($role->is_system)
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Sistem') }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-slate-600">{{ $role->code }}</td>
                            <td class="text-slate-700">{{ $role->users_count }}</td>
                            <td class="text-slate-700">{{ $role->code === $lockedCode ? __('Semua') : $role->permissions_count }}</td>
                            <td class="text-right">
                                @if($role->code === $lockedCode)
                                    <span class="text-xs text-slate-400">{{ __('Terkunci') }}</span>
                                @else
                                    @hasPermission('roles.update')
                                        <button wire:click="edit({{ $role->id }})" class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Ubah') }}</button>
                                    @endhasPermission
                                    @hasPermission('roles.delete')
                                        @unless($role->is_system)
                                            <button wire:click="delete({{ $role->id }})" wire:confirm="{{ __('Hapus peran :name?', ['name' => $role->name]) }}"
                                                    class="ml-3 text-sm font-semibold text-rose-600 hover:text-rose-700">{{ __('Hapus') }}</button>
                                        @endunless
                                    @endhasPermission
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-bpjs.card>
    @endif
</div>
