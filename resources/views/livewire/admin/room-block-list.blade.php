<div>
    {{-- Filter bar --}}
    <div class="card bpjs-rise mb-4" style="padding: 16px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <x-bpjs.field label="Status" for="statusFilter">
            <select wire:model.live="statusFilter" id="statusFilter" class="select" style="min-width: 180px;">
                <option value="active">Aktif</option>
                <option value="cancelled">Dibatalkan</option>
                <option value="all">Semua</option>
            </select>
        </x-bpjs.field>

        <div style="margin-left: auto;">
            @hasPermission('rooms.manage-blocks')
                <x-bpjs.button variant="primary" icon="plus" :href="route('admin.room-blocks.create')" wire:navigate>
                    Blokir Ruangan
                </x-bpjs.button>
            @endhasPermission
        </div>
    </div>

    {{-- Table --}}
    <div class="card bpjs-rise" style="overflow: hidden;">
        <table class="dtable">
            <thead>
                <tr>
                    <th>Ruangan</th>
                    <th>Jenis</th>
                    <th>Judul</th>
                    <th>Waktu</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($blocks as $block)
                    <tr>
                        <td>
                            <div class="font-semibold text-slate-900">{{ $block->room->name ?? '—' }}</div>
                        </td>
                        <td>
                            <x-bpjs.pill variant="slate">{{ $block->block_type->label() }}</x-bpjs.pill>
                        </td>
                        <td class="text-slate-900">
                            <span class="inline-flex items-center gap-2">
                                {{ $block->title }}
                                @if($block->recurrence_group_id !== null)
                                    <x-bpjs.pill variant="blue">Berulang</x-bpjs.pill>
                                @endif
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-slate-600">
                            <span class="mono">{{ $block->starts_at->format('d M Y H:i') }} – {{ $block->ends_at->format('H:i') }}</span>
                        </td>
                        <td>
                            @if($block->cancelled_at !== null)
                                <x-bpjs.pill variant="slate">Dibatalkan</x-bpjs.pill>
                            @else
                                <x-bpjs.pill variant="amber">Aktif</x-bpjs.pill>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($block->cancelled_at === null)
                                @hasPermission('rooms.manage-blocks')
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <x-bpjs.button variant="danger" wire:click="cancel({{ $block->id }})"
                                                       wire:confirm="Batalkan blokir &quot;{{ $block->title }}&quot;?" type="button"
                                                       class="!px-3 !py-1.5 !text-xs !rounded-lg">Batalkan</x-bpjs.button>
                                        @if($block->recurrence_group_id !== null)
                                            <x-bpjs.button variant="solid-danger" wire:click="cancelSeries({{ $block->id }})"
                                                           wire:confirm="Batalkan SEMUA jadwal aktif dalam seri ini?" type="button"
                                                           class="!px-3 !py-1.5 !text-xs !rounded-lg">Batalkan Seri</x-bpjs.button>
                                        @endif
                                    </div>
                                @endhasPermission
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400" style="padding: 48px;">Tidak ada blokir ruangan.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($blocks->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $blocks->links() }}</div>
        @endif
    </div>
</div>
