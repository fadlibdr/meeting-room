<div>
    {{-- Filters --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <x-bpjs.field :label="__('Cari kode atau nama')" for="search">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" id="search"
                               placeholder="{{ __('contoh: projector') }}" class="input pl-10" />
                    </div>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field :label="__('Kategori')" for="categoryFilter">
                    <select wire:model.live="categoryFilter" id="categoryFilter" class="select">
                        <option value="">{{ __('Semua kategori') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field :label="__('Status')" for="statusFilter">
                    <select wire:model.live="statusFilter" id="statusFilter" class="select">
                        <option value="all">{{ __('Semua') }}</option>
                        <option value="active">{{ __('Aktif') }}</option>
                        <option value="inactive">{{ __('Nonaktif') }}</option>
                    </select>
                </x-bpjs.field>
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between gap-3">
            <button wire:click="clearFilters" type="button"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800">{{ __('Reset filter') }}</button>
            @hasPermission('rooms.create')
                <x-bpjs.button :href="route('admin.facilities.create')" icon="plus" wire:navigate>
                    {{ __('Tambah Fasilitas') }}
                </x-bpjs.button>
            @endhasPermission
        </div>
    </div>

    {{-- Table --}}
    <div class="card bpjs-rise">
        <table class="dtable">
            <thead>
                <tr>
                    <th>{{ __('Kode') }}</th>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Kategori') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($facilities as $facility)
                    <tr>
                        <td class="font-mono text-slate-900">{{ $facility->code }}</td>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-bpjs-blue-50 text-bpjs-blue-600 flex-shrink-0">
                                    <x-icon :name="$facility->icon ?: 'panelLeft'" :size="17" />
                                </span>
                                <span class="font-semibold text-slate-900">{{ $facility->name }}</span>
                            </div>
                        </td>
                        <td>
                            @if($facility->category)
                                <x-bpjs.pill variant="blue">{{ ucfirst($facility->category) }}</x-bpjs.pill>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td>
                            @if($facility->is_active)
                                <x-bpjs.pill variant="green">{{ __('Aktif') }}</x-bpjs.pill>
                            @else
                                <x-bpjs.pill variant="slate">{{ __('Nonaktif') }}</x-bpjs.pill>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-3">
                                @hasPermission('rooms.update')
                                    <a href="{{ route('admin.facilities.edit', $facility->id) }}" wire:navigate
                                       class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Edit') }}</a>
                                    <button wire:click="toggleActive({{ $facility->id }})"
                                            wire:confirm="{{ $facility->is_active ? __('Nonaktifkan :name?', ['name' => $facility->name]) : __('Aktifkan :name?', ['name' => $facility->name]) }}"
                                            type="button"
                                            class="text-sm font-semibold {{ $facility->is_active ? 'text-red-700 hover:text-red-800' : 'text-bpjs-green-600 hover:text-bpjs-green-700' }}">
                                        {{ $facility->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                                    </button>
                                @endhasPermission
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-500" style="padding: 48px 16px;">
                            {{ __('Tidak ada fasilitas yang sesuai dengan filter.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($facilities->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">{{ $facilities->links() }}</div>
        @endif
    </div>
</div>
