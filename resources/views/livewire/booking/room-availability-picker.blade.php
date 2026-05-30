<div>
    <div class="space-y-4">
        {{-- Status hint when window not set (Dec-4=A) --}}
        @if ($startsAt === '' || $endsAt === '')
            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm text-slate-600">
                    <span class="font-medium">Pilih waktu mulai dan selesai</span> di atas untuk melihat ketersediaan setiap ruangan.
                </p>
            </div>
        @endif

        {{-- Card grid: 1 col mobile, 2 col sm, 3 col md+ (Dec-3=B) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            @foreach ($rooms as $room)
                @php($state = $availability[$room->id] ?? ['status' => 'unknown', 'conflictTitle' => null, 'exceedsCapacity' => false])
                @php($isSelected = $selectedRoomId === $room->id)
                @php($isAvailable = $state['status'] === 'available')
                @php($isUnavailable = $state['status'] === 'unavailable')
                @php($isUnknown = $state['status'] === 'unknown')

                <button type="button" wire:click="selectRoom({{ $room->id }})" wire:key="room-card-{{ $room->id }}" aria-pressed="{{ $isSelected ? 'true' : 'false' }}" class="relative text-left p-4 rounded-lg border-2 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bpjs-blue-500
                    @if ($isSelected) bg-bpjs-blue-50 border-bpjs-blue-500 shadow-sm
                    @elseif ($isAvailable) bg-white border-bpjs-green-200 hover:border-bpjs-green-400 hover:bg-bpjs-green-50
                    @elseif ($isUnavailable) bg-white border-amber-200 hover:border-amber-300
                    @else bg-white border-slate-200 hover:border-slate-300
                    @endif">

                    {{-- Selection checkmark (top-right when selected) --}}
                    @if ($isSelected)
                        <div class="absolute top-2 right-2 w-6 h-6 rounded-full bg-bpjs-blue-500 flex items-center justify-center" aria-hidden="true">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    @endif

                    {{-- Room name + capacity --}}
                    <h3 class="font-display text-sm font-semibold text-slate-900 pr-7 truncate">
                        {{ $room->name }}
                    </h3>
                    <p class="mt-0.5 text-[11px] text-slate-500 truncate">
                        @if ($room->floor || $room->location)
                            {{ trim(($room->floor ?? '').($room->floor && $room->location ? ' · ' : '').($room->location ?? '')) }} ·
                        @endif
                        Kapasitas {{ $room->capacity }}
                    </p>

                    {{-- Availability badge --}}
                    <div class="mt-3 flex items-center gap-1.5">
                        @if ($isAvailable)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide bg-bpjs-green-50 text-bpjs-green-800 border border-bpjs-green-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                Tersedia
                            </span>
                        @elseif ($isUnavailable)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide bg-amber-50 text-amber-800 border border-amber-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                </svg>
                                Tidak Tersedia
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide bg-slate-50 text-slate-500 border border-slate-200">
                                Belum Diperiksa
                            </span>
                        @endif
                    </div>

                    {{-- Conflict reason (when unavailable) --}}
                    @if ($isUnavailable && $state['conflictTitle'] !== null)
                        <p class="mt-2 text-[11px] text-amber-700 line-clamp-2" title="{{ $state['conflictTitle'] }}">
                            {{ $state['conflictTitle'] }}
                        </p>
                    @endif

                    {{-- Capacity warning (Dec-5=B: advisory, not blocking) --}}
                    @if ($state['exceedsCapacity'] && $attendeeCount > 0)
                        <p class="mt-2 text-[11px] text-bpjs-blue-700 flex items-start gap-1">
                            <svg class="w-3 h-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Peserta ({{ $attendeeCount }}) melebihi kapasitas ruangan</span>
                        </p>
                    @endif
                </button>
            @endforeach
        </div>

        @if (count($rooms) === 0)
            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-6 text-center">
                <p class="text-sm text-slate-500">
                    Tidak ada ruangan aktif yang tersedia.
                </p>
            </div>
        @endif
    </div>
</div>
