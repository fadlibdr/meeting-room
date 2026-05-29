<div>
    <div class="mb-4 flex items-center gap-3">
        <label for="statusFilter" class="text-sm font-medium text-gray-700">Status</label>
        <select wire:model.live="statusFilter" id="statusFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="active">Aktif</option>
            <option value="cancelled">Dibatalkan</option>
            <option value="all">Semua</option>
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruang</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($blocks as $block)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $block->room->name ?? '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $block->block_type->label() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $block->title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $block->starts_at->format('d M Y H:i') }} – {{ $block->ends_at->format('H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($block->cancelled_at !== null)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Dibatalkan</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Aktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if($block->cancelled_at === null)
                                @hasPermission('rooms.manage-blocks')
                                    <button wire:click="cancel({{ $block->id }})" wire:confirm="Batalkan blokir &quot;{{ $block->title }}&quot;?" type="button" class="text-red-600 hover:text-red-900">Batalkan</button>
                                @endhasPermission
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">Tidak ada blokir ruang.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($blocks->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">{{ $blocks->links() }}</div>
        @endif
    </div>
</div>
