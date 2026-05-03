<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Page Header --}}
            <div class="mb-6">
                <h1 class="font-display text-3xl font-semibold text-slate-900 tracking-tight">
                    Kalender Reservasi
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Lihat ketersediaan ruangan per hari. Klik slot kosong untuk membuat reservasi baru.
                </p>
            </div>

            {{-- Date Navigation Row --}}
            <div class="mb-4 bg-white rounded-lg border border-slate-200 shadow-sm px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousDay"
                            aria-label="Hari sebelumnya"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <input
                            type="date"
                            wire:model.live="selectedDate"
                            aria-label="Pilih tanggal"
                            class="rounded-md border-slate-300 text-slate-900 shadow-sm focus:border-bpjs-blue-500 focus:ring-bpjs-blue-500 sm:text-sm"
                        />

                        <button
                            type="button"
                            wire:click="nextDay"
                            aria-label="Hari berikutnya"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            wire:click="setToday"
                            class="inline-flex items-center px-3 py-2 rounded-md border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            Hari Ini
                        </button>
                    </div>

                    <p class="font-display text-sm font-medium text-slate-700">
                        {{ $displayDate }}
                    </p>
                </div>
            </div>

            {{-- Room Filter Pills --}}
            <div class="mb-4 bg-white rounded-lg border border-slate-200 shadow-sm px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 mr-1">
                        Filter Ruangan:
                    </span>
                    @foreach ($allRooms as $room)
                        @php($isActive = empty($roomFilterIds) || in_array($room->id, $roomFilterIds))
                        <button
                            type="button"
                            wire:click="toggleRoom({{ $room->id }})"
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium transition-colors
                                @if ($isActive)
                                    bg-bpjs-blue-50 text-bpjs-blue-700 border border-bpjs-blue-200 hover:bg-bpjs-blue-100
                                @else
                                    bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100
                                @endif"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        >
                            {{ $room->name }}
                        </button>
                    @endforeach
                    @if (! empty($roomFilterIds))
                        <button
                            type="button"
                            wire:click="$set('roomFilterIds', [])"
                            class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors ml-2"
                        >
                            Reset filter
                        </button>
                    @endif
                </div>
            </div>

            {{-- Calendar Grid --}}
            @if (empty($timeWindow['slots']))
                {{-- Closed day or no rooms match filter --}}
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm px-6 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <h3 class="mt-3 font-display text-base font-medium text-slate-900">
                        @if (empty($rooms))
                            Tidak ada ruangan yang dipilih
                        @else
                            Semua ruangan tutup
                        @endif
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        @if (empty($rooms))
                            Pilih setidaknya satu ruangan dari filter di atas.
                        @else
                            Tidak ada jam operasional untuk {{ $displayDate }}.
                        @endif
                    </p>
                </div>
            @else
                @if ($bookings->isEmpty())
                    <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Belum ada reservasi untuk {{ $displayDate }}. Klik slot kosong untuk membuat reservasi pertama.
                    </div>
                @endif

                {{-- DESKTOP: Time-grid by room (md+) --}}
                <div class="hidden md:block bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <div
                            class="grid min-w-fit"
                            style="grid-template-columns: 80px repeat({{ count($rooms) }}, minmax(160px, 1fr)); grid-template-rows: 40px repeat({{ count($timeWindow['slots']) }}, 40px);"
                        >
                            {{-- Header: empty corner --}}
                            <div class="bg-slate-100 border-b border-r border-slate-200 sticky left-0 z-20"
                                 style="grid-column: 1; grid-row: 1;"></div>

                            {{-- Header: Room columns --}}
                            @foreach ($rooms as $i => $room)
                                <div
                                    class="bg-slate-100 border-b border-r border-slate-200 px-3 py-2 flex flex-col justify-center"
                                    style="grid-column: {{ $i + 2 }}; grid-row: 1;"
                                >
                                    <p class="font-display text-sm font-semibold text-slate-900 truncate">
                                        {{ $room->name }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 truncate">
                                        Kapasitas {{ $room->capacity }}
                                    </p>
                                </div>
                            @endforeach

                            {{-- Time labels (column 1) + alternating-row backgrounds --}}
                            @foreach ($timeWindow['slots'] as $rowIdx => $slot)
                                <div
                                    class="border-r border-b border-slate-100 px-2 py-1 text-[11px] font-mono text-slate-500 sticky left-0 z-10
                                        @if ($rowIdx % 2 === 0) bg-slate-50 @else bg-white @endif"
                                    style="grid-column: 1; grid-row: {{ $rowIdx + 2 }};"
                                >
                                    {{ $slot }}
                                </div>

                                {{-- Empty cells per room (M1-D-Dec-4: clickable links) --}}
                                @foreach ($rooms as $i => $room)
                                    
                                        href="{{ $this->emptyCellHref($room->id, $slot) }}"
                                        wire:navigate
                                        class="border-r border-b border-slate-100 transition-colors group flex items-center justify-center
                                            @if ($rowIdx % 2 === 0) bg-slate-50 hover:bg-bpjs-blue-50 @else bg-white hover:bg-bpjs-blue-50 @endif"
                                        style="grid-column: {{ $i + 2 }}; grid-row: {{ $rowIdx + 2 }};"
                                        aria-label="Buat reservasi {{ $room->name }} pukul {{ $slot }}"
                                    >
                                        <svg class="w-4 h-4 text-bpjs-blue-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </a>
                                @endforeach
                            @endforeach

                            {{-- Booking blocks (overlay on top of empty cells via z-index + grid placement) --}}
                            @foreach ($bookings as $booking)
                                @php
                                    $position = $this->bookingGridPosition($booking);
                                    $roomIndex = $rooms->search(fn ($r) => $r->id === $booking->room_id);
                                @endphp
                                @if ($position !== null && $roomIndex !== false)
                                    <div
                                        class="relative z-10 m-0.5 rounded-md border px-2 py-1 overflow-hidden flex flex-col justify-start
                                            @if ($booking->status->value === 'approved')
                                                bg-bpjs-green-50 border-bpjs-green-300 text-bpjs-green-900
                                            @else
                                                bg-amber-50 border-amber-300 text-amber-900
                                            @endif"
                                        style="grid-column: {{ $roomIndex + 2 }}; grid-row: {{ $position['rowStart'] }} / span {{ $position['rowSpan'] }};"
                                        title="{{ $booking->subject }} — {{ $this->formatBookingTime($booking) }} — {{ $booking->requester->name ?? '?' }}"
                                    >
                                        <p class="text-[11px] font-medium leading-tight truncate">
                                            {{ $booking->subject }}
                                        </p>
                                        <p class="text-[10px] opacity-75 leading-tight truncate">
                                            {{ $this->formatBookingTime($booking) }}
                                        </p>
                                        @if ($position['rowSpan'] >= 2)
                                            <p class="text-[10px] opacity-60 leading-tight truncate mt-auto">
                                                {{ $booking->requester->name ?? '?' }}
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- MOBILE: List view grouped by room (M1-E) --}}
                <div class="md:hidden space-y-3">
                    {{-- CTA: direct link to form (replaces empty-cell click that doesn't fit mobile) --}}
                    
                    <a
                        href="{{ route('bookings.new') }}"
                        wire:navigate
                        class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-bpjs-blue-500 hover:bg-bpjs-blue-600 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Reservasi Baru
                    </a>

                    {{-- Per-room sections, sorted alphabetically --}}
                    @foreach ($rooms as $room)
                        @php($roomBookings = $bookings->where('room_id', $room->id)->sortBy('starts_at')->values())
                        <section class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                            <header class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                                <h3 class="font-display text-sm font-semibold text-slate-900 truncate">
                                    {{ $room->name }}
                                </h3>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">
                                    @if ($room->floor || $room->location)
                                        {{ trim(($room->floor ?? '').($room->floor && $room->location ? ' · ' : '').($room->location ?? '')) }} ·
                                    @endif
                                    Kapasitas {{ $room->capacity }}
                                </p>
                            </header>

                            @if ($roomBookings->isEmpty())
                                <div class="px-4 py-4">
                                    <p class="text-xs text-slate-500 italic">
                                        Tidak ada reservasi untuk hari ini.
                                    </p>
                                    
                                        href="{{ route('bookings.new', ['room_id' => $room->id]) }}"
                                        wire:navigate
                                        class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-bpjs-blue-600 hover:text-bpjs-blue-700"
                                    >
                                        Reservasi ruangan ini
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            @else
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($roomBookings as $booking)
                                        <li class="px-4 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-mono text-xs text-slate-500">
                                                        {{ $this->formatBookingTime($booking) }}
                                                    </p>
                                                    <p class="mt-1 text-sm font-medium text-slate-900 truncate">
                                                        {{ $booking->subject }}
                                                    </p>
                                                    <p class="text-xs text-slate-500 truncate">
                                                        {{ $booking->requester->name ?? '—' }}
                                                    </p>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide flex-shrink-0
                                                    @if ($booking->status->value === 'approved')
                                                        bg-bpjs-green-50 text-bpjs-green-800 border border-bpjs-green-200
                                                    @else
                                                        bg-amber-50 text-amber-800 border border-amber-200
                                                    @endif">
                                                    @if ($booking->status->value === 'approved') Disetujui @else Menunggu @endif
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
