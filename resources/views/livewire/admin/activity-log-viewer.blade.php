<div class="space-y-6">
    {{-- Filters --}}
    <x-bpjs.card>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <x-bpjs.field :label="__('Modul')" for="moduleFilter">
                <select wire:model.live="moduleFilter" id="moduleFilter" class="select">
                    <option value="">{{ __('Semua modul') }}</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </x-bpjs.field>
            <x-bpjs.field :label="__('Aksi')" for="eventFilter">
                <select wire:model.live="eventFilter" id="eventFilter" class="select">
                    <option value="">{{ __('Semua aksi') }}</option>
                    @foreach($events as $e)
                        <option value="{{ $e }}">{{ $e }}</option>
                    @endforeach
                </select>
            </x-bpjs.field>
            <x-bpjs.field :label="__('Dari tanggal')" for="dateFrom">
                <input wire:model.live="dateFrom" type="date" id="dateFrom" class="input" />
            </x-bpjs.field>
            <x-bpjs.field :label="__('Sampai tanggal')" for="dateTo">
                <input wire:model.live="dateTo" type="date" id="dateTo" class="input" />
            </x-bpjs.field>
            <x-bpjs.field :label="__('Cari deskripsi')" for="search">
                <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="{{ __('kata kunci') }}" class="input" />
            </x-bpjs.field>
        </div>
        <div class="mt-4">
            <x-bpjs.button variant="ghost" icon="x" wire:click="clearFilters">{{ __('Reset filter') }}</x-bpjs.button>
        </div>
    </x-bpjs.card>

    {{-- Log table --}}
    @php
        $eventPill = [
            'created'   => 'blue',
            'submitted' => 'amber',
            'approved'  => 'green',
            'rejected'  => 'red',
            'cancelled' => 'slate',
        ];
    @endphp
    <x-bpjs.card :pad="false">
        <table class="dtable">
            <thead>
                <tr>
                    <th>{{ __('Waktu') }}</th>
                    <th>{{ __('Aktor') }}</th>
                    <th>{{ __('Modul') }}</th>
                    <th>{{ __('Aksi') }}</th>
                    <th>{{ __('Deskripsi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="font-mono whitespace-nowrap" style="color: var(--slate-500);">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="whitespace-nowrap" style="color: var(--slate-900); font-weight: 500;">{{ $log->actor->name ?? __('Sistem') }}</td>
                        <td class="whitespace-nowrap" style="color: var(--slate-500);">{{ ucfirst($log->module) }}</td>
                        <td class="whitespace-nowrap"><x-bpjs.pill :variant="$eventPill[$log->event] ?? 'slate'">{{ $log->event }}</x-bpjs.pill></td>
                        <td>{{ $log->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center" style="padding: 48px 16px; color: var(--slate-500);">{{ __('Tidak ada log aktivitas yang sesuai dengan filter.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($logs->hasPages())
            <div style="padding: 12px 16px; border-top: 1px solid var(--slate-100);">{{ $logs->links() }}</div>
        @endif
    </x-bpjs.card>
</div>
