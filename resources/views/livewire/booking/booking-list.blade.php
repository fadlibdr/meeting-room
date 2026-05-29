<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-800">Daftar Booking</h1>
            @if($canCreate)
                <a href="{{ route('bookings.new') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    + Buat Booking
                </a>
            @endif
        </div>

        <div class="mb-6 bg-white rounded-lg shadow-sm p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model.live="statusFilter" id="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Semua status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-1">Dari tanggal</label>
                    <input wire:model.live="dateFrom" type="date" id="dateFrom" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                </div>
                <div>
                    <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-1">Sampai tanggal</label>
                    <input wire:model.live="dateTo" type="date" id="dateTo" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                </div>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari subjek / kode</label>
                    <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="kata kunci" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                </div>
            </div>
            <div class="mt-4">
                <button wire:click="clearFilters" type="button" class="text-sm text-gray-500 hover:text-gray-700">Reset filter</button>
            </div>
        </div>

        @php
            $statusClasses = [
                'gray' => 'bg-gray-100 text-gray-700',
                'amber' => 'bg-amber-100 text-amber-800',
                'green' => 'bg-green-100 text-green-800',
                'red' => 'bg-red-100 text-red-800',
                'blue' => 'bg-blue-100 text-blue-800',
            ];
        @endphp
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjek</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruang</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                        @if($canViewAll)
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pemohon</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">{{ $booking->booking_code }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $booking->subject }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $booking->room?->name ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $this->displayDateTime($booking) }}</td>
                            @if($canViewAll)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $booking->requester?->name ?? '—' }}</td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClasses[$booking->status->color()] ?? 'bg-gray-100 text-gray-700' }}">{{ $booking->status->label() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('bookings.show', $booking) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canViewAll ? 7 : 6 }}" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada booking yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($bookings->hasPages())
                <div class="px-6 py-3 border-t border-gray-200">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</div>
