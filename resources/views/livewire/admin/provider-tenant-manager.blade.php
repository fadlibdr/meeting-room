<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($showForm)
        <form wire:submit="save" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <x-bpjs.field :label="__('Nama Organisasi')" req for="tname" :error="$errors->first('name')">
                    <input wire:model="name" type="text" id="tname" placeholder="PT Contoh Sejahtera"
                           class="input @error('name') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Slug')" for="tslug"
                              :hint="__('Opsional. Dibuat otomatis dari nama bila kosong. Dipakai sebagai subdomain.')"
                              :error="$errors->first('slug')">
                    <input wire:model="slug" type="text" id="tslug" placeholder="contoh-sejahtera"
                           class="input @error('slug') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Domain Utama')" for="tdomain"
                              :hint="__('Opsional. Domain khusus penyewa (mis. booking.contoh.co.id).')"
                              :error="$errors->first('primaryDomain')">
                    <input wire:model="primaryDomain" type="text" id="tdomain"
                           class="input @error('primaryDomain') input--err @enderror" />
                </x-bpjs.field>
                <p class="text-xs text-slate-500">{{ __('Membuat penyewa juga menyiapkan peran, izin, dan pengaturan default-nya.') }}</p>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancel">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ __('Buat Penyewa') }}</x-bpjs.button>
            </div>
        </form>
    @else
        <div class="flex justify-end mb-4">
            <x-bpjs.button icon="plus" wire:click="newTenant">{{ __('Tambah Penyewa') }}</x-bpjs.button>
        </div>
        <div class="card bpjs-rise">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Slug') }}</th>
                        <th>{{ __('Domain') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td class="font-semibold text-slate-900">
                                {{ $tenant->name }}
                                @if($tenant->is_default)<x-bpjs.pill variant="blue">{{ __('Platform') }}</x-bpjs.pill>@endif
                            </td>
                            <td class="mono text-slate-500" style="font-size:12px;">{{ $tenant->slug }}</td>
                            <td class="text-slate-500" style="font-size:12px;">{{ $tenant->primary_domain ?: '—' }}</td>
                            <td>
                                @if($tenant->status === 'active')
                                    <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="slate">{{ __('Ditangguhkan') }}</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-right">
                                @unless($tenant->is_default)
                                    <button wire:click="toggle({{ $tenant->id }})" type="button"
                                            class="text-sm font-semibold text-slate-500 hover:text-slate-800">
                                        {{ $tenant->status === 'active' ? __('Tangguhkan') : __('Aktifkan') }}
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-500" style="padding:40px 16px;">{{ __('Belum ada penyewa.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
