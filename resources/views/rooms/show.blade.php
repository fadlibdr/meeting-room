<x-app-layout :title="$room->name" :subtitle="$room->code">
    @php
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $hours = $room->operatingHours->keyBy('day_of_week');
    @endphp

    <a href="{{ route('rooms.index') }}" wire:navigate
       class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">
        <x-icon name="chevronLeft" :size="16" /> {{ __('Kembali ke Daftar Ruang') }}
    </a>

    <div class="grid grid-cols-1 gap-[18px] lg:grid-cols-3">
        {{-- Photo + key facts --}}
        <div class="lg:col-span-2">
            <x-bpjs.card :pad="false" class="overflow-hidden">
                @if ($room->photoUrl())
                    <img src="{{ $room->photoUrl() }}" alt="{{ $room->name }}"
                         style="width: 100%; height: 280px; object-fit: cover; display: block;" />
                @else
                    <div style="height: 200px; background: linear-gradient(135deg, #00538f, #00416d); display:flex; align-items:center; justify-content:center;">
                        <span style="color: rgba(255,255,255,.5);"><x-icon name="building" :size="48" :stroke="1.4" /></span>
                    </div>
                @endif
                <div class="card--pad">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-700">
                        <span class="flex items-center gap-1.5"><x-icon name="users" :size="16" /> {{ __('Kapasitas') }} <strong>{{ $room->capacity }}</strong></span>
                        @if ($room->location || $room->floor)
                            <span class="flex items-center gap-1.5"><x-icon name="mapPin" :size="16" />
                                {{ trim(($room->floor ?? '').($room->floor && $room->location ? ' · ' : '').($room->location ?? '')) }}</span>
                        @endif
                    </div>
                    @if ($room->description)
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">{{ $room->description }}</p>
                    @endif

                    @if ($room->facilityItems->isNotEmpty())
                        <div class="mt-5">
                            <div class="eyebrow mb-2">{{ __('Fasilitas') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($room->facilityItems as $item)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                        {{ $item->facility?->name }}@if($item->quantity > 1) ×{{ $item->quantity }}@endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-bpjs.card>
        </div>

        {{-- Operating hours + CTA --}}
        <div class="space-y-[18px]">
            @hasPermission('bookings.create')
                <x-bpjs.button block icon="plus" :href="route('bookings.new', ['room_id' => $room->id])" wire:navigate>
                    {{ __('Pesan Ruang Ini') }}
                </x-bpjs.button>
            @endhasPermission

            <x-bpjs.card :title="__('Jam Operasional')">
                <dl class="space-y-1.5 text-sm">
                    @for ($d = 1; $d <= 6; $d++)
                        @php($h = $hours->get($d))
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ $days[$d] }}</dt>
                            <dd class="font-mono text-slate-800">
                                @if ($h && ! $h->is_closed && $h->open_time)
                                    {{ substr((string) $h->open_time, 0, 5) }}–{{ substr((string) $h->close_time, 0, 5) }}
                                @else
                                    {{ __('Tutup') }}
                                @endif
                            </dd>
                        </div>
                    @endfor
                    @php($sun = $hours->get(0))
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ $days[0] }}</dt>
                        <dd class="font-mono text-slate-800">
                            @if ($sun && ! $sun->is_closed && $sun->open_time)
                                {{ substr((string) $sun->open_time, 0, 5) }}–{{ substr((string) $sun->close_time, 0, 5) }}
                            @else
                                {{ __('Tutup') }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-bpjs.card>
        </div>
    </div>
</x-app-layout>
