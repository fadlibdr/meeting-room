<div>
    {{-- Filters --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <x-bpjs.field label="Cari kode atau nama" for="search">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" id="search"
                               placeholder="contoh: BIRO-UMUM" class="input pl-10" />
                    </div>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field label="Status" for="statusFilter">
                    <select wire:model.live="statusFilter" id="statusFilter" class="select">
                        <option value="all">Semua</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </x-bpjs.field>
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between gap-3">
            <button wire:click="clearFilters" type="button"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800">Reset filter</button>
            @hasPermission('users.create')
                <x-bpjs.button :href="route('admin.units.create')" icon="plus" wire:navigate>
                    Tambah Unit
                </x-bpjs.button>
            @endhasPermission
        </div>
    </div>

    {{-- Table --}}
    <div class="card bpjs-rise">
        <table class="dtable">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Induk</th>
                    <th>Pengguna</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($units as $unit)
                    <tr>
                        <td class="font-mono text-slate-900">{{ $unit->code }}</td>
                        <td><span class="font-semibold text-slate-900">{{ $unit->name }}</span></td>
                        <td>
                            @if($unit->parent)
                                <span class="text-slate-600">{{ $unit->parent->name }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td><span class="font-mono text-slate-600">{{ $unit->users_count }}</span></td>
                        <td>
                            @if($unit->is_active)
                                <x-bpjs.pill variant="green">Aktif</x-bpjs.pill>
                            @else
                                <x-bpjs.pill variant="slate">Nonaktif</x-bpjs.pill>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-3">
                                @hasPermission('users.update')
                                    <a href="{{ route('admin.units.edit', $unit->id) }}" wire:navigate
                                       class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">Edit</a>
                                    <button wire:click="toggleActive({{ $unit->id }})"
                                            wire:confirm="{{ $unit->is_active ? 'Nonaktifkan ' : 'Aktifkan ' }}{{ $unit->name }}?"
                                            type="button"
                                            class="text-sm font-semibold {{ $unit->is_active ? 'text-red-700 hover:text-red-800' : 'text-bpjs-green-600 hover:text-bpjs-green-700' }}">
                                        {{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                @endhasPermission
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-slate-500" style="padding: 48px 16px;">
                            Belum ada unit. Klik "Tambah Unit" untuk membuat yang pertama.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($units->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">{{ $units->links() }}</div>
        @endif
    </div>
</div>
