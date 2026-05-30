<div>
    {{-- Filters --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari kode, nama, atau lokasi</label>
                <input wire:model.live.debounce.300ms="search" type="text" id="search"
                       placeholder="contoh: Garuda atau RM-"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
            <div>
                <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select wire:model.live="statusFilter" id="statusFilter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="all">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                    <option value="archived">Arsip</option>
                </select>
            </div>
            <div>
                <label for="approvalFilter" class="block text-sm font-medium text-gray-700 mb-1">Mode Approval</label>
                <select wire:model.live="approvalFilter" id="approvalFilter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua mode</option>
                    @foreach($approvalModes as $mode)
                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <button wire:click="clearFilters" type="button" class="text-sm text-gray-500 hover:text-gray-700">Reset filter</button>
            @hasPermission('rooms.create')
                <a href="{{ route('admin.rooms.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                    + Tambah Ruang
                </a>
            @endhasPermission
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kapasitas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode Approval</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($rooms as $room)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $room->code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $room->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $room->location ?? '-' }}@if($room->floor) · {{ $room->floor }}@endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $room->capacity }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $room->approval_mode->label() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $badge = match($room->status) {
                                    \App\Enums\RoomStatus::Active => 'bg-green-100 text-green-800',
                                    \App\Enums\RoomStatus::Inactive => 'bg-yellow-100 text-yellow-800',
                                    \App\Enums\RoomStatus::Archived => 'bg-gray-200 text-gray-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">
                                {{ $room->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            @hasPermission('rooms.update')
                                <a href="{{ route('admin.rooms.edit', $room->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                @if($room->status !== \App\Enums\RoomStatus::Active)
                                    <button wire:click="activate({{ $room->id }})" wire:confirm="Aktifkan kembali {{ $room->name }}?"
                                            type="button" class="text-green-600 hover:text-green-900">Aktifkan</button>
                                @endif
                            @endhasPermission
                            @hasPermission('rooms.delete')
                                @if($room->status === \App\Enums\RoomStatus::Active)
                                    <button wire:click="deactivate({{ $room->id }})" wire:confirm="Nonaktifkan {{ $room->name }}?"
                                            type="button" class="text-yellow-600 hover:text-yellow-900">Nonaktifkan</button>
                                @endif
                                @if($room->status !== \App\Enums\RoomStatus::Archived)
                                    <button wire:click="archive({{ $room->id }})" wire:confirm="Arsipkan {{ $room->name }}? Ruang tidak akan muncul untuk pemesanan baru."
                                            type="button" class="text-red-600 hover:text-red-900">Arsipkan</button>
                                @endif
                            @endhasPermission
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">Tidak ada ruang yang sesuai dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($rooms->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">{{ $rooms->links() }}</div>
        @endif
    </div>
</div>
