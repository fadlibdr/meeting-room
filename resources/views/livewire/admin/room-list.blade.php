<div>
    {{-- Filters --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <x-bpjs.field :label="__('Cari kode, nama, atau lokasi')" for="search">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" :size="18" />
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" id="search"
                               placeholder="{{ __('contoh: Garuda atau RM-') }}"
                               class="input pl-10" />
                    </div>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field :label="__('Status')" for="statusFilter">
                    <select wire:model.live="statusFilter" id="statusFilter" class="select">
                        <option value="all">{{ __('Semua') }}</option>
                        <option value="active">{{ __('Aktif') }}</option>
                        <option value="inactive">{{ __('Nonaktif') }}</option>
                        <option value="archived">{{ __('Arsip') }}</option>
                    </select>
                </x-bpjs.field>
            </div>
            <div>
                <x-bpjs.field :label="__('Mode Approval')" for="approvalFilter">
                    <select wire:model.live="approvalFilter" id="approvalFilter" class="select">
                        <option value="">{{ __('Semua mode') }}</option>
                        @foreach($approvalModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <button wire:click="clearFilters" type="button"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800">{{ __('Reset filter') }}</button>
            @hasPermission('rooms.create')
                <x-bpjs.button :href="route('admin.rooms.create')" icon="plus" wire:navigate>
                    {{ __('Tambah Ruangan') }}
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
                    <th>{{ __('Lokasi') }}</th>
                    <th>{{ __('Kapasitas') }}</th>
                    <th>{{ __('Mode Approval') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rooms as $room)
                    <tr>
                        <td class="font-mono text-slate-900">{{ $room->code }}</td>
                        <td class="font-semibold text-slate-900">
                            <div class="flex items-center gap-2.5">
                                @if($room->photoUrl())
                                    <img src="{{ $room->photoUrl() }}" alt="{{ $room->name }}"
                                         class="h-9 w-9 rounded-md border border-slate-200 object-cover" />
                                @endif
                                <span>{{ $room->name }}</span>
                            </div>
                        </td>
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
                                       class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Edit') }}</a>
                                    @if($room->status !== \App\Enums\RoomStatus::Active)
                                        <button wire:click="activate({{ $room->id }})" wire:confirm="{{ __('Aktifkan kembali :name?', ['name' => $room->name]) }}"
                                                type="button" class="text-sm font-semibold text-bpjs-green-600 hover:text-bpjs-green-700">{{ __('Aktifkan') }}</button>
                                    @endif
                                @endhasPermission
                                @hasPermission('rooms.delete')
                                    @if($room->status === \App\Enums\RoomStatus::Active)
                                        <button wire:click="deactivate({{ $room->id }})" wire:confirm="{{ __('Nonaktifkan :name?', ['name' => $room->name]) }}"
                                                type="button" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('Nonaktifkan') }}</button>
                                    @endif
                                    @if($room->status !== \App\Enums\RoomStatus::Archived)
                                        <button wire:click="archive({{ $room->id }})" wire:confirm="{{ __('Arsipkan :name? Ruang tidak akan muncul untuk pemesanan baru.', ['name' => $room->name]) }}"
                                                type="button" class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Arsipkan') }}</button>
                                    @endif
                                @endhasPermission
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-slate-500" style="padding: 48px 16px;">
                            {{ __('Tidak ada ruang yang sesuai dengan filter.') }}
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
