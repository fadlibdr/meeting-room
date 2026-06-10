<div class="bpjs-rise">

    {{-- Toolbar: prev / today / next + date label + legend --}}
    <x-bpjs.card class="card--pad mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-bpjs.button variant="ghost" wire:click="previous" aria-label="{{ __('Sebelumnya') }}" class="!px-2.5">
                    <x-icon name="chevronLeft" :size="18" />
                </x-bpjs.button>

                <x-bpjs.button variant="ghost" wire:click="setToday" icon="calendar">
                    {{ __('Hari Ini') }}
                </x-bpjs.button>

                <x-bpjs.button variant="ghost" wire:click="next" aria-label="{{ __('Berikutnya') }}" class="!px-2.5">
                    <x-icon name="chevronRight" :size="18" />
                </x-bpjs.button>

                <label class="ml-1 flex items-center gap-2 rounded-[10px] border border-slate-300 bg-white px-3 py-2 focus-within:border-bpjs-blue-500">
                    <x-icon name="calendar" :size="16" class="text-slate-400" />
                    <input type="date" wire:model.live="selectedDate" aria-label="{{ __('Pilih tanggal') }}"
                        class="border-0 p-0 font-mono text-sm text-slate-900 focus:ring-0" />
                </label>

                <span class="h-display ml-1 hidden text-sm font-semibold text-slate-900 sm:inline">
                    {{ $rangeLabel }}
                </span>
            </div>

            <div class="flex items-center gap-4">
                {{-- View switcher: Day / Week / Month --}}
                <div class="inline-flex rounded-[10px] border border-slate-200 bg-slate-50 p-0.5">
                    @foreach (['day' => __('Hari'), 'week' => __('Minggu'), 'month' => __('Bulan')] as $mode => $label)
                        <button type="button" wire:click="setView('{{ $mode }}')" @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                            'bg-white text-bpjs-blue-700 shadow-sm ring-1 ring-inset ring-slate-200' => $view === $mode,
                            'text-slate-500 hover:text-slate-700' => $view !== $mode,
                        ])>{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="hidden items-center gap-4 sm:flex">
                <span class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm bg-bpjs-green-500"></span>
                    {{ __('Disetujui') }}
                </span>
                <span class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400"></span>
                    {{ __('Menunggu') }}
                </span>
                </div>
            </div>
        </div>
    </x-bpjs.card>

    {{-- Room filter chips --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="eyebrow mr-1">{{ __('Ruangan') }}</span>
        @foreach ($allRooms as $room)
            @php($isActive = empty($roomFilterIds) || in_array($room->id, $roomFilterIds))
            <button type="button" wire:click="toggleRoom({{ $room->id }})" aria-pressed="{{ $isActive ? 'true' : 'false' }}" @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                'bg-bpjs-blue-50 text-bpjs-blue-700 ring-1 ring-inset ring-bpjs-blue-200 hover:bg-bpjs-blue-100' => $isActive,
                'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-200' => ! $isActive,
            ])>
                <x-icon name="building" :size="14" />
                {{ $room->name }}
            </button>
        @endforeach
        @if (! empty($roomFilterIds))
            <button type="button" wire:click="$set('roomFilterIds', [])" class="ml-1 inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-700">
                <x-icon name="x" :size="14" />
                {{ __('Reset filter') }}
            </button>
        @endif
    </div>

    {{-- Calendar grid --}}
    @if ($view === 'day')
    @if (empty($timeWindow['slots']))
        <x-bpjs.card class="px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <x-icon name="calendar" :size="28" />
            </span>
            <h3 class="h-display mt-4 text-base font-semibold text-slate-900">
                {{ empty($rooms) ? __('Tidak ada ruangan yang dipilih') : __('Semua ruangan tutup') }}
            </h3>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                {{ empty($rooms) ? __('Pilih setidaknya satu ruangan dari filter di atas.') : __('Tidak ada jam operasional untuk :date', ['date' => $displayDate]) }}
            </p>
        </x-bpjs.card>
    @else
        @if ($bookings->isEmpty())
            <div class="mb-3 flex items-center gap-2 rounded-[10px] bg-bpjs-blue-50 px-4 py-3 text-sm text-bpjs-blue-700 ring-1 ring-inset ring-bpjs-blue-100">
                <x-icon name="info" :size="18" class="shrink-0" />
                {{ __('Belum ada reservasi untuk :date. Klik slot kosong untuk membuat reservasi pertama.', ['date' => $displayDate]) }}
            </div>
        @endif

        {{-- DESKTOP: time-grid by room --}}
        <x-bpjs.card class="hidden overflow-hidden md:block">
            <div class="overflow-x-auto">
                <div class="grid min-w-fit" style="grid-template-columns: 70px repeat({{ count($rooms) }}, minmax(150px, 1fr)); grid-template-rows: 44px repeat({{ count($timeWindow['slots']) }}, 40px);">
                    <div class="sticky left-0 z-20 border-b border-r border-slate-200 bg-slate-50" style="grid-column: 1; grid-row: 1;"></div>

                    @foreach ($rooms as $i => $room)
                        <div class="flex flex-col justify-center border-b border-r border-slate-200 bg-slate-50 px-3 py-2" style="grid-column: {{ $i + 2 }}; grid-row: 1;">
                            <p class="h-display truncate text-sm font-semibold text-slate-900">
                                {{ $room->name }}
                            </p>
                            <p class="flex items-center gap-1 truncate text-[11px] text-slate-500">
                                <x-icon name="users" :size="12" />
                                {{ $room->capacity }}
                            </p>
                        </div>
                    @endforeach

                    @foreach ($timeWindow['slots'] as $rowIdx => $slot)
                        <div @class([
                            'sticky left-0 z-10 border-b border-r border-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500',
                            'bg-slate-50' => $rowIdx % 2 === 0,
                            'bg-white' => $rowIdx % 2 !== 0,
                        ]) style="grid-column: 1; grid-row: {{ $rowIdx + 2 }};">
                            {{ $slot }}
                        </div>

                        @foreach ($rooms as $i => $room)
                            <a href="{{ $this->emptyCellHref($room->id, $slot) }}" wire:navigate aria-label="{{ __('Buat reservasi :room pukul :slot', ['room' => $room->name, 'slot' => $slot]) }}" @class([
                                'cell flex items-center justify-center border-b border-r border-slate-100 transition-colors',
                                'bg-slate-50' => $rowIdx % 2 === 0,
                                'bg-white' => $rowIdx % 2 !== 0,
                            ]) style="grid-column: {{ $i + 2 }}; grid-row: {{ $rowIdx + 2 }};">
                                <span class="cellplus text-bpjs-blue-400" style="opacity:0">
                                    <x-icon name="plus" :size="16" />
                                </span>
                            </a>
                        @endforeach
                    @endforeach

                    {{-- Booking blocks overlay (using @php() one-liners) --}}
                    @foreach ($bookings as $booking)
                        @php($position = $this->bookingGridPosition($booking))
                        @php($roomIndex = $rooms->search(fn ($r) => $r->id === $booking->resource_id))
                        @php($sv = $booking->status->value)
                        @if ($position !== null && $roomIndex !== false)
                            <div wire:key="booking-block-{{ $booking->id }}" @class([
                                'relative z-10 m-0.5 flex flex-col justify-start overflow-hidden rounded-lg px-2 py-1 ring-1 ring-inset',
                                'bg-bpjs-green-50 text-bpjs-green-900 ring-bpjs-green-300' => $sv === 'approved',
                                'bg-slate-100 text-slate-700 ring-slate-300' => $sv === 'completed',
                                'bg-amber-50 text-amber-900 ring-amber-300' => ! in_array($sv, ['approved', 'completed']),
                            ]) style="grid-column: {{ $roomIndex + 2 }}; grid-row: {{ $position['rowStart'] }} / span {{ $position['rowSpan'] }};" title="{{ $booking->subject }} - {{ $this->formatBookingTime($booking) }} - {{ $booking->requester->name ?? '?' }}">
                                <p class="truncate text-[11px] font-semibold leading-tight">
                                    {{ $booking->subject }}
                                </p>
                                <p class="truncate font-mono text-[10px] leading-tight opacity-75">
                                    {{ $this->formatBookingTime($booking) }}
                                </p>
                                @if ($position['rowSpan'] >= 2)
                                    <p class="mt-auto truncate text-[10px] leading-tight opacity-60">
                                        {{ $booking->requester->name ?? '?' }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </x-bpjs.card>

        {{-- MOBILE: List view grouped by room --}}
        <div class="space-y-3 md:hidden">
            <x-bpjs.button variant="primary" icon="plus" block :href="route('bookings.new')" wire:navigate>
                {{ __('Buat Reservasi Baru') }}
            </x-bpjs.button>

            @foreach ($rooms as $room)
                @php($roomBookings = $bookings->where('resource_id', $room->id)->sortBy('starts_at')->values())
                <x-bpjs.card wire:key="room-mobile-{{ $room->id }}" class="overflow-hidden">
                    <header class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                        <h3 class="h-display truncate text-sm font-semibold text-slate-900">
                            {{ $room->name }}
                        </h3>
                        <p class="mt-0.5 truncate text-[11px] text-slate-500">
                            @if ($room->floor || $room->location)
                                {{ trim(($room->floor ?? '').($room->floor && $room->location ? ' - ' : '').($room->location ?? '')) }} ·
                            @endif
                            {{ __('Kapasitas') }} {{ $room->capacity }}
                        </p>
                    </header>

                    @if ($roomBookings->isEmpty())
                        <div class="px-4 py-4">
                            <p class="text-xs italic text-slate-500">
                                {{ __('Tidak ada reservasi untuk hari ini.') }}
                            </p>
                            <a href="{{ route('bookings.new', ['room_id' => $room->id]) }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">
                                {{ __('Reservasi ruangan ini') }}
                                <x-icon name="chevronRight" :size="14" />
                            </a>
                        </div>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($roomBookings as $booking)
                                <li wire:key="mobile-booking-{{ $booking->id }}" class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="flex items-center gap-1 font-mono text-xs text-slate-500">
                                                <x-icon name="clock" :size="13" />
                                                {{ $this->formatBookingTime($booking) }}
                                            </p>
                                            <p class="mt-1 truncate text-sm font-semibold text-slate-900">
                                                {{ $booking->subject }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ $booking->requester->name ?? '-' }}
                                            </p>
                                        </div>
                                        <x-bpjs.status-pill :status="$booking->status" class="flex-shrink-0" />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-bpjs.card>
            @endforeach
        </div>
    @endif

    @elseif ($view === 'week')
        <x-bpjs.card class="overflow-hidden">
            <div class="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-7 sm:divide-x sm:divide-y-0">
                @foreach ($weekDays as $day)
                    @php($key = $day->format('Y-m-d'))
                    @php($dayBookings = collect($bookingsByDay[$key] ?? []))
                    <div class="min-h-[120px] {{ $day->isToday() ? 'bg-bpjs-blue-50/40' : '' }}">
                        <button type="button" wire:click="goToDay('{{ $key }}')"
                                class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                            <span class="text-xs font-semibold uppercase tracking-wide {{ $day->isToday() ? 'text-bpjs-blue-700' : 'text-slate-500' }}">
                                {{ $day->locale(app()->getLocale())->isoFormat('ddd') }}
                            </span>
                            <span class="font-mono text-sm {{ $day->isToday() ? 'font-bold text-bpjs-blue-700' : 'text-slate-900' }}">{{ $day->locale(app()->getLocale())->isoFormat('D MMM') }}</span>
                        </button>
                        <div class="space-y-1 p-2">
                            @forelse ($dayBookings as $b)
                                @php($sv = $b->status->value)
                                <a href="{{ route('bookings.show', $b) }}" wire:navigate @class([
                                    'block rounded-md px-2 py-1 ring-1 ring-inset',
                                    'bg-bpjs-green-50 text-bpjs-green-900 ring-bpjs-green-200' => $sv === 'approved',
                                    'bg-slate-100 text-slate-700 ring-slate-200' => $sv === 'completed',
                                    'bg-amber-50 text-amber-900 ring-amber-200' => ! in_array($sv, ['approved', 'completed']),
                                ])>
                                    <span class="block truncate font-mono text-[10px] opacity-75">{{ $this->formatBookingTime($b) }}</span>
                                    <span class="block truncate text-[11px] font-semibold leading-tight">{{ $b->subject }}</span>
                                    <span class="block truncate text-[10px] opacity-70">{{ $b->room?->name }}</span>
                                </a>
                            @empty
                                <p class="px-1 py-2 text-center text-[11px] text-slate-400">—</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </x-bpjs.card>

    @elseif ($view === 'month')
        <x-bpjs.card class="overflow-hidden">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                @foreach ($weekDays as $day)
                    <div class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        {{ $day->locale(app()->getLocale())->isoFormat('ddd') }}
                    </div>
                @endforeach
            </div>
            @foreach ($monthGrid as $week)
                <div class="grid grid-cols-7">
                    @foreach ($week as $cell)
                        @php($key = $cell['date']->format('Y-m-d'))
                        @php($dayBookings = collect($bookingsByDay[$key] ?? []))
                        <button type="button" wire:click="goToDay('{{ $key }}')" @class([
                            'flex min-h-[96px] flex-col items-stretch gap-1 border-b border-r border-slate-100 p-1.5 text-left hover:bg-slate-50',
                            'bg-slate-50/60' => ! $cell['inMonth'],
                        ])>
                            <span @class([
                                'font-mono text-xs',
                                'font-bold text-bpjs-blue-700' => $cell['date']->isToday(),
                                'text-slate-400' => ! $cell['inMonth'],
                                'text-slate-700' => $cell['inMonth'] && ! $cell['date']->isToday(),
                            ])>{{ $cell['date']->isoFormat('D') }}</span>
                            @foreach ($dayBookings->take(3) as $b)
                                @php($sv = $b->status->value)
                                <span @class([
                                    'block truncate rounded px-1 py-0.5 text-[10px] leading-tight',
                                    'bg-bpjs-green-100 text-bpjs-green-800' => $sv === 'approved',
                                    'bg-slate-200 text-slate-700' => $sv === 'completed',
                                    'bg-amber-100 text-amber-800' => ! in_array($sv, ['approved', 'completed']),
                                ]) title="{{ $b->subject }}">{{ $b->subject }}</span>
                            @endforeach
                            @if ($dayBookings->count() > 3)
                                <span class="text-[10px] font-medium text-slate-500">+{{ $dayBookings->count() - 3 }} {{ __('lainnya') }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach
        </x-bpjs.card>
    @endif

</div>
