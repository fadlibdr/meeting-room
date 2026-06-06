<div>
    @if(session('status'))
        <p class="bpjs-fade" style="margin-bottom: 12px; border: 1px solid var(--bpjs-blue-200); background: var(--bpjs-blue-50); color: var(--bpjs-blue-700); border-radius: 10px; padding: 10px 12px; font-size: 13px;">{{ session('status') }}</p>
    @endif

    {{-- Filter bar --}}
    <x-bpjs.card :pad="false" class="bpjs-rise" style="padding: 16px; margin-bottom: 16px;">
        <div class="flex gap-3 flex-wrap items-end">
            <x-bpjs.field :label="__('Status')" for="statusFilter">
                <select wire:model.live="statusFilter" id="statusFilter" class="select" style="min-width: 160px;">
                    <option value="">{{ __('Semua status') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-bpjs.field>

            <x-bpjs.field :label="__('Dari Tanggal')" for="dateFrom">
                <input wire:model.live="dateFrom" type="date" id="dateFrom" class="input" style="min-width: 150px;" />
            </x-bpjs.field>

            <x-bpjs.field :label="__('Sampai Tanggal')" for="dateTo">
                <input wire:model.live="dateTo" type="date" id="dateTo" class="input" style="min-width: 150px;" />
            </x-bpjs.field>

            <x-bpjs.field :label="__('Cari subjek / kode')" for="search">
                <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="{{ __('kata kunci') }}" class="input" style="min-width: 220px;" />
            </x-bpjs.field>

            <div class="flex gap-2 items-center" style="margin-left: auto;">
                <span class="text-sm text-slate-400">{{ __('Ekspor') }}:</span>
                <x-bpjs.button variant="ghost" icon="download" wire:click="export('csv')" wire:loading.attr="disabled">CSV</x-bpjs.button>
                <x-bpjs.button variant="ghost" icon="download" wire:click="export('xlsx')" wire:loading.attr="disabled">Excel</x-bpjs.button>
                @if($canCreate)
                    <x-bpjs.button :href="route('bookings.new')" icon="plus" wire:navigate>{{ __('Tambah') }}</x-bpjs.button>
                @endif
            </div>
        </div>

        @php
            $activeFilters = array_filter([
                'status' => $statusFilter,
                'from' => $dateFrom,
                'to' => $dateTo,
                'q' => $search,
            ], static fn ($v) => $v !== '');
        @endphp
        @if(count($activeFilters) > 0)
            <div class="flex items-center gap-2 flex-wrap" style="margin-top: 14px;">
                <span style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--slate-400);">{{ __('Filter aktif') }}</span>

                @if($statusFilter !== '')
                    <x-bpjs.status-pill :status="$statusFilter" />
                @endif
                @if($dateFrom !== '' || $dateTo !== '')
                    <x-bpjs.pill variant="blue">{{ $dateFrom !== '' ? $dateFrom : '…' }} – {{ $dateTo !== '' ? $dateTo : '…' }}</x-bpjs.pill>
                @endif
                @if($search !== '')
                    <x-bpjs.pill variant="slate">"{{ $search }}"</x-bpjs.pill>
                @endif

                <button wire:click="clearFilters" type="button" style="background: none; border: 0; font-size: 12px; color: var(--slate-500); font-weight: 600; cursor: pointer;">{{ __('Hapus semua') }}</button>
            </div>
        @endif
    </x-bpjs.card>

    {{-- Result count --}}
    <div class="flex items-baseline justify-between" style="margin: 0 4px 10px;">
        <span style="font-size: 12.5px; color: var(--slate-500);">
            <strong style="color: var(--slate-800); font-weight: 700;">{{ $bookings->total() }}</strong> {{ __('reservasi') }}
        </span>
    </div>

    {{-- Table --}}
    <x-bpjs.card :pad="false" class="bpjs-rise">
        <table class="dtable">
            <thead>
                <tr>
                    <th>{{ __('Kode') }}</th>
                    <th>{{ __('Subjek') }}</th>
                    <th>{{ __('Ruangan') }}</th>
                    <th>{{ __('Jam') }}</th>
                    @if($canViewAll)
                        <th>{{ __('Pemesan') }}</th>
                    @endif
                    <th>{{ __('Status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td class="mono" style="color: var(--slate-500);">{{ $booking->booking_code }}</td>
                        <td style="font-weight: 600; color: var(--slate-900);">{{ $booking->subject }}</td>
                        <td style="color: var(--slate-600);">{{ $booking->room?->name ?? '—' }}</td>
                        <td class="mono" style="color: var(--slate-600);">{{ $this->displayDateTime($booking) }}</td>
                        @if($canViewAll)
                            <td style="color: var(--slate-600);">{{ $booking->requester?->name ?? '—' }}</td>
                        @endif
                        <td><x-bpjs.status-pill :status="$booking->status" /></td>
                        <td class="text-right">
                            <a href="{{ route('bookings.show', $booking) }}" wire:navigate style="color: var(--bpjs-blue-600); font-weight: 600;">{{ __('Lihat') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canViewAll ? 7 : 6 }}" style="padding: 48px; text-align: center; color: var(--slate-400);">
                            {{ __('Tidak ada reservasi yang cocok.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($bookings->hasPages())
            <div style="padding: 14px 16px; border-top: 1px solid var(--slate-100);">{{ $bookings->links() }}</div>
        @endif
    </x-bpjs.card>
</div>
