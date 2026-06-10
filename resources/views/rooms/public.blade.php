<x-app-layout title="Ruangan" subtitle="Daftar ruang rapat tersedia">

    @php
        $rooms = \App\Models\Room::with('facilityItems.facility')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        // Gradient tints for the photo placeholder, cycled per card.
        $tints = [
            ['#0066B3', '#00416d'],
            ['#00B140', '#008e33'],
            ['#005490', '#003459'],
            ['#33cf73', '#006c26'],
        ];
    @endphp

    @if ($rooms->isEmpty())
        <x-bpjs.card rise class="text-center" style="padding: 56px 24px;">
            <div class="mx-auto flex items-center justify-center" style="width: 64px; height: 64px; border-radius: 9999px; background: var(--bpjs-blue-50); color: var(--bpjs-blue-600);">
                <x-icon name="building" :size="34" />
            </div>
            <p class="h-display font-bold text-slate-900 mt-4" style="font-size: 18px;">
                Belum ada ruangan
            </p>
            <p class="mt-1.5 text-slate-500" style="font-size: 13px;">
                Belum ada ruang rapat yang tersedia saat ini.
            </p>
        </x-bpjs.card>
    @else
        <div class="grid gap-[18px]" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            @foreach ($rooms as $i => $room)
                @php [$a, $b] = $tints[$i % count($tints)]; @endphp
                <a href="{{ route('rooms.show', $room) }}" wire:navigate wire:key="room-{{ $room->id }}"
                   class="block transition-transform hover:-translate-y-0.5">
                <x-bpjs.card :pad="false" rise
                    style="overflow: hidden; animation-delay: {{ $i * 40 }}ms;">

                    {{-- Photo (falls back to a gradient placeholder when none is set) --}}
                    <div style="padding: 10px; padding-bottom: 0;">
                        @if ($room->photoUrl())
                            <div style="height: 132px; border-radius: 12px; overflow: hidden;">
                                <img src="{{ $room->photoUrl() }}" alt="{{ $room->name }}"
                                     loading="lazy"
                                     style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                            </div>
                        @else
                            <div style="height: 132px; border-radius: 12px; position: relative; overflow: hidden;
                                        background: linear-gradient(135deg, {{ $a }}, {{ $b }});
                                        display: flex; align-items: center; justify-content: center;">
                                <div style="position: absolute; width: 180px; height: 180px; border-radius: 9999px;
                                            background: radial-gradient(circle, rgba(255,255,255,.12), transparent 60%);
                                            top: -70px; right: -40px;"></div>
                                <span style="color: rgba(255,255,255,.5);"><x-icon name="building" :size="36" :stroke="1.4" /></span>
                                <span style="position: absolute; bottom: 9px; left: 11px; font-size: 10.5px; font-weight: 600;
                                             letter-spacing: .04em; text-transform: uppercase; color: rgba(255,255,255,.6);">
                                    Foto ruangan
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div style="padding: 18px;">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="h-display font-bold text-slate-900" style="font-size: 17px;">
                                    {{ $room->name }}
                                </div>
                                @if ($room->floor)
                                    <div class="mt-0.5 flex items-center gap-1.5 text-slate-500" style="font-size: 12.5px;">
                                        <x-icon name="mapPin" :size="14" />
                                        {{ $room->floor }}
                                    </div>
                                @endif
                            </div>
                            <x-bpjs.pill variant="green">Aktif</x-bpjs.pill>
                        </div>

                        <div class="flex items-center gap-1.5 font-semibold text-slate-700" style="font-size: 13px; margin: 14px 0;">
                            <x-icon name="users" :size="16" />
                            {{ $room->capacity }} kursi
                        </div>

                        @if ($room->facilityItems->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($room->facilityItems as $item)
                                    @if ($item->facility)
                                        <x-bpjs.pill variant="slate" style="font-weight: 500;">
                                            {{ $item->facility->name }}
                                        </x-bpjs.pill>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-bpjs.card>
                </a>
            @endforeach
        </div>
    @endif

</x-app-layout>
