<div>
    <div class="mb-6 bg-white rounded-lg shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari kode atau nama</label>
                <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="contoh: projector"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
            <div>
                <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select wire:model.live="categoryFilter" id="categoryFilter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Semua kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select wire:model.live="statusFilter" id="statusFilter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="all">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between">
            <button wire:click="clearFilters" type="button" class="text-sm text-gray-500 hover:text-gray-700">Reset filter</button>
            @hasPermission('rooms.create')
                <a href="{{ route('admin.facilities.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">+ Tambah Fasilitas</a>
            @endhasPermission
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($facilities as $facility)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $facility->code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $facility->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $facility->category ? ucfirst($facility->category) : '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($facility->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            @hasPermission('rooms.update')
                                <a href="{{ route('admin.facilities.edit', $facility->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                <button wire:click="toggleActive({{ $facility->id }})"
                                        wire:confirm="{{ $facility->is_active ? 'Nonaktifkan ' : 'Aktifkan ' }}{{ $facility->name }}?"
                                        type="button"
                                        class="{{ $facility->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                    {{ $facility->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            @endhasPermission
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Tidak ada fasilitas yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($facilities->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">{{ $facilities->links() }}</div>
        @endif
    </div>
</div>
