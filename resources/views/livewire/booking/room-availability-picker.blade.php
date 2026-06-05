<div>
    <div class="space-y-4">
        {{-- Status hint when window not set (Dec-4=A) --}}
        @if ($startsAt === '' || $endsAt === '')
            <div class="flex items-center gap-2 rounded-[10px] px-3.5 py-2.5 text-sm text-slate-600" style="background: var(--slate-50); border: 1px solid var(--slate-200);">
                <span class="text-slate-400 flex-shrink-0"><x-icon name="clock" :size="16" /></span>
                <span><span class="font-semibold text-slate-700">Pilih waktu mulai dan selesai</span> di atas untuk melihat ketersediaan setiap ruangan.</span>
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

                <button type="button" wire:click="selectRoom({{ $room->id }})" wire:key="room-card-{{ $room->id }}" aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    class="relative text-left rounded-[13px] overflow-hidden transition-all focus:outline-none bpjs-pop"
                    style="border: 2px solid {{ $isSelected ? 'var(--bpjs-blue-500)' : ($isAvailable ? 'var(--bpjs-green-200)' : ($isUnavailable ? 'var(--amber-200)' : 'var(--slate-200)')) }}; background: {{ $isSelected ? 'var(--bpjs-blue-50)' : '#fff' }};">

                    {{-- Photo placeholder: gradient + building icon --}}
                    <div class="relative flex items-center justify-center" style="height: 92px; background: linear-gradient(135deg, var(--bpjs-blue-600) 0%, var(--bpjs-blue-800) 100%);">
                        <span style="color: rgba(255,255,255,.55);"><x-icon name="building" :size="34" /></span>

                        {{-- Selection checkmark (top-right when selected) --}}
                        @if ($isSelected)
                            <div class="absolute top-2.5 right-2.5 w-6 h-6 rounded-full flex items-center justify-center" style="background: var(--bpjs-blue-500); color: #fff;" aria-hidden="true">
                                <x-icon name="check" :size="14" :stroke="3" />
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
                        {{-- Room name --}}
                        <h3 class="h-display text-[14.5px] font-bold text-slate-900 truncate">
                            {{ $room->name }}
                        </h3>
                        <p class="mt-0.5 text-[11.5px] text-slate-500 truncate">
                            @if ($room->floor || $room->location)
                                {{ trim(($room->floor ?? '').($room->floor && $room->location ? ' · ' : '').($room->location ?? '')) }} ·
                            @endif
                            Kapasitas {{ $room->capacity }}
                        </p>

                        {{-- Facility / info pills --}}
                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                            @if ($room->code)
                                <span class="pill pill--slate font-mono">{{ $room->code }}</span>
                            @endif
                            @if ($room->floor)
                                <span class="pill pill--slate">{{ $room->floor }}</span>
                            @endif
                            <span class="pill pill--blue">{{ $room->capacity }} orang</span>
                        </div>

                        {{-- Availability badge --}}
                        <div class="mt-3 flex items-center gap-1.5">
                            @if ($isAvailable)
                                <span class="pill pill--green">
                                    <x-icon name="check" :size="12" :stroke="3" /> Tersedia
                                </span>
                            @elseif ($isUnavailable)
                                <span class="pill pill--amber">
                                    <x-icon name="alert" :size="12" /> Tidak Tersedia
                                </span>
                            @else
                                <span class="pill pill--slate">Belum Diperiksa</span>
                            @endif
                        </div>

                        {{-- Conflict reason (when unavailable) --}}
                        @if ($isUnavailable && $state['conflictTitle'] !== null)
                            <p class="mt-2 text-[11px] text-amber-700 line-clamp-2" title="{{ $state['conflictTitle'] }}">
                                Bentrok: {{ $state['conflictTitle'] }}
                            </p>
                        @endif

                        {{-- Capacity warning (Dec-5=B: advisory, not blocking) --}}
                        @if ($state['exceedsCapacity'] && $attendeeCount > 0)
                            <p class="mt-2 text-[11px] text-bpjs-blue-700 flex items-start gap-1">
                                <span class="flex-shrink-0 mt-px"><x-icon name="info" :size="13" /></span>
                                <span>Peserta ({{ $attendeeCount }}) melebihi kapasitas ruangan</span>
                            </p>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>

        @if (count($rooms) === 0)
            <div class="card card--pad text-center" style="background: var(--slate-50);">
                <p class="text-sm text-slate-500">
                    Tidak ada ruangan aktif yang tersedia.
                </p>
            </div>
        @endif
    </div>
</div>
