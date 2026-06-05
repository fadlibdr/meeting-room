<div>
    {{-- Filters --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <x-bpjs.field label="Cari kode, nama, atau lokasi" for="search">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" id="search"
                               placeholder="contoh: Garuda atau RM-"
                               class="input pl-10" />
                    </div>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field label="Status" for="statusFilter">
                    <select wire:model.live="statusFilter" id="statusFilter" class="select">
                        <option value="all">Semua</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="archived">Arsip</option>
                    </select>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field label="Mode Approval" for="approvalFilter">
                    <select wire:model.live="approvalFilter" id="approvalFilter" class="select">
                        <option value="">Semua mode</option>
                        @foreach($approvalModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <button wire:click="clearFilters" type="button"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800">Reset filter</button>
            @hasPermission('rooms.create')
                <x-bpjs.button :href="route('admin.rooms.create')" icon="plus" wire:navigate>
                    Tambah Ruangan
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
                    <th>Lokasi</th>
                    <th>Kapasitas</th>
                    <th>Mode Approval</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rooms as $room)
                    <tr>
                        <td class="font-mono text-slate-900">{{ $room->code }}</td>
                        <td class="font-semibold text-slate-900">{{ $room->name }}</td>
                        <td class="text-slate-500">
                            {{ $room->location ?? '-' }}@if($room->floor) · {{ $room->floor }}@endif
                        </td>
                        <td class="font-mono text-slate-700">{{ $room->capacity }}</td>
                        <td class="text-slate-600">{{ $room->approval_mode->label() }}</td>
                        <td>
                            @php
                                $variant = match($room->status) {
                                    \App\Enums\RoomStatus::Active => 'green',
                                    \App\Enums\RoomStatus::Inactive => 'amber',
                                    \App\Enums\RoomStatus::Archived => 'slate',
                                };
                            @endphp
                            <x-bpjs.pill :variant="$variant">{{ $room->status->label() }}</x-bpjs.pill>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-3">
                                @hasPermission('rooms.update')
                                    <a href="{{ route('admin.rooms.edit', $room->id) }}" wire:navigate
                                       class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">Edit</a>
                                    @if($room->status !== \App\Enums\RoomStatus::Active)
                                        <button wire:click="activate({{ $room->id }})" wire:confirm="Aktifkan kembali {{ $room->name }}?"
                                                type="button" class="text-sm font-semibold text-bpjs-green-600 hover:text-bpjs-green-700">Aktifkan</button>
                                    @endif
                                @endhasPermission
                                @hasPermission('rooms.delete')
                                    @if($room->status === \App\Enums\RoomStatus::Active)
                                        <button wire:click="deactivate({{ $room->id }})" wire:confirm="Nonaktifkan {{ $room->name }}?"
                                                type="button" class="text-sm font-semibold text-amber-700 hover:text-amber-800">Nonaktifkan</button>
                                    @endif
                                    @if($room->status !== \App\Enums\RoomStatus::Archived)
                                        <button wire:click="archive({{ $room->id }})" wire:confirm="Arsipkan {{ $room->name }}? Ruang tidak akan muncul untuk pemesanan baru."
                                                type="button" class="text-sm font-semibold text-red-700 hover:text-red-800">Arsipkan</button>
                                    @endif
                                @endhasPermission
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-slate-500" style="padding: 48px 16px;">
                            Tidak ada ruang yang sesuai dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($rooms->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">{{ $rooms->links() }}</div>
        @endif
    </div>
</div>
