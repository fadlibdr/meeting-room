<div>
    <div class="mb-6 bg-white rounded-lg shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label for="moduleFilter" class="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                <select wire:model.live="moduleFilter" id="moduleFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua modul</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="eventFilter" class="block text-sm font-medium text-gray-700 mb-1">Aksi</label>
                <select wire:model.live="eventFilter" id="eventFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua aksi</option>
                    @foreach($events as $e)
                        <option value="{{ $e }}">{{ $e }}</option>
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
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari deskripsi</label>
                <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="kata kunci" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
        </div>
        <div class="mt-4">
            <button wire:click="clearFilters" type="button" class="text-sm text-gray-500 hover:text-gray-700">Reset filter</button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modul</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->actor->name ?? 'Sistem' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($log->module) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $log->event }}</span></td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $log->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Tidak ada log aktivitas yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($logs->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
