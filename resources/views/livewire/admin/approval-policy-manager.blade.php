<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700); display: inline-flex;"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($showForm)
        {{-- Editor --}}
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field :label="__('Nama Kebijakan')" req for="name" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="name" class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center cursor-pointer gap-2">
                            <input type="checkbox" wire:model="isActive"
                                   class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                            <span class="text-sm text-slate-700">{{ __('Aktif') }}</span>
                        </label>
                    </div>
                </div>

                <x-bpjs.field :label="__('Deskripsi')" for="description" :error="$errors->first('description')">
                    <textarea wire:model="description" id="description" rows="2" class="textarea"></textarea>
                </x-bpjs.field>

                {{-- Steps --}}
                <div>
                    <div class="field__lbl mb-2">{{ __('Langkah Persetujuan') }}</div>
                    <div class="space-y-3">
                        @foreach($steps as $i => $step)
                            <div wire:key="step-{{ $i }}" class="card card--pad" style="background: var(--slate-50);">
                                <div class="flex items-start gap-3 flex-wrap">
                                    <span class="pill pill--slate font-mono mt-2">{{ $i + 1 }}</span>
                                    <div class="flex-1" style="min-width: 160px;">
                                        <select wire:model.live="steps.{{ $i }}.type" class="select">
                                            @foreach($stepTypes as $type)
                                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if($step['type'] === 'role')
                                        <div class="flex-1" style="min-width: 180px;">
                                            <select wire:model="steps.{{ $i }}.role_id" class="select @error('steps.'.$i.'.role_id') input--err @enderror">
                                                <option value="">{{ __('— Pilih peran —') }}</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('steps.'.$i.'.role_id') <p class="field__err">{{ $message }}</p> @enderror
                                        </div>
                                    @elseif($step['type'] === 'user')
                                        <div class="flex-1" style="min-width: 220px;">
                                            <select wire:model="steps.{{ $i }}.approver_user_id" class="select @error('steps.'.$i.'.approver_user_id') input--err @enderror">
                                                <option value="">{{ __('— Pilih pengguna —') }}</option>
                                                @foreach($approverUsers as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                                @endforeach
                                            </select>
                                            @error('steps.'.$i.'.approver_user_id') <p class="field__err">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                    <button type="button" wire:click="removeStep({{ $i }})"
                                            class="mt-2 text-slate-400 hover:text-red-600" title="{{ __('Hapus') }}">
                                        <x-icon name="x" :size="18" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('steps') <p class="field__err mt-2">{{ $message }}</p> @enderror
                    <button type="button" wire:click="addStep" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">
                        <x-icon name="plus" :size="16" /> {{ __('Tambah Langkah') }}
                    </button>
                </div>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancel">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ $editingId ? __('Simpan Perubahan') : __('Buat Kebijakan') }}</x-bpjs.button>
            </div>
        </form>
    @else
        {{-- List --}}
        <div class="flex justify-end mb-4">
            <x-bpjs.button icon="plus" wire:click="newPolicy">{{ __('Tambah Kebijakan') }}</x-bpjs.button>
        </div>

        <div class="card bpjs-rise">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Langkah') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $policy)
                        <tr>
                            <td>
                                <span class="font-semibold text-slate-900">{{ $policy->name }}</span>
                                @if($policy->description)
                                    <span class="block text-xs text-slate-400">{{ $policy->description }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-slate-700">{{ $policy->steps_count }}</td>
                            <td>
                                @if($policy->is_active)
                                    <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ __('Nonaktif') }}</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <button wire:click="editPolicy({{ $policy->id }})" type="button"
                                            class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Edit') }}</button>
                                    <button wire:click="delete({{ $policy->id }})" wire:confirm="{{ __('Hapus kebijakan :name?', ['name' => $policy->name]) }}" type="button"
                                            class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Hapus') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500" style="padding: 40px 16px;">{{ __('Belum ada kebijakan persetujuan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
