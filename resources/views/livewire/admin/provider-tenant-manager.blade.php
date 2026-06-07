<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    @if($editingId)
        <form wire:submit="saveBranding" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <h3 class="text-sm font-semibold text-slate-800">{{ __('White-label Branding') }}</h3>
                <x-bpjs.field :label="__('Nama Merek')" for="bname" :error="$errors->first('brandName')">
                    <input wire:model="brandName" type="text" id="bname" placeholder="Contoh Corp"
                           class="input @error('brandName') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('Warna Merek (hex)')" for="bcolor"
                              :hint="__('Mis. #1A73E8 — dipakai sebagai aksen + theme-color.')"
                              :error="$errors->first('brandColor')">
                    <input wire:model="brandColor" type="text" id="bcolor" placeholder="#1A73E8"
                           class="input mono @error('brandColor') input--err @enderror" />
                </x-bpjs.field>
                <x-bpjs.field :label="__('URL Logo')" for="blogo" :error="$errors->first('logoUrl')">
                    <input wire:model="logoUrl" type="url" id="blogo" placeholder="https://contoh.co.id/logo.png"
                           class="input @error('logoUrl') input--err @enderror" />
                </x-bpjs.field>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="cancelBranding">{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" icon="check">{{ __('Simpan Branding') }}</x-bpjs.button>
            </div>
        </form>
    @elseif($showForm)
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
                                <div class="inline-flex items-center justify-end gap-3">
                                    <button wire:click="editBranding({{ $tenant->id }})" type="button"
                                            class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Branding') }}</button>
                                    @unless($tenant->is_default)
                                        <button wire:click="toggle({{ $tenant->id }})" type="button"
                                                class="text-sm font-semibold text-slate-500 hover:text-slate-800">
                                            {{ $tenant->status === 'active' ? __('Tangguhkan') : __('Aktifkan') }}
                                        </button>
                                    @endunless
                                </div>
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
