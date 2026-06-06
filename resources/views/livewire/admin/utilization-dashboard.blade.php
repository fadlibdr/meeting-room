<div class="py-2">
    @php($r = $this->report)

    {{-- Filter bar --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="from" class="field__lbl">Dari tanggal</label>
                <input type="date" id="from" wire:model.live="from" class="input" max="{{ $to }}" />
            </div>
            <div>
                <label for="to" class="field__lbl">Sampai tanggal</label>
                <input type="date" id="to" wire:model.live="to" class="input" min="{{ $from }}" />
            </div>
            <div class="md:col-span-2">
                <span class="field__lbl">Rentang cepat</span>
                <div class="flex flex-wrap gap-2">
                    @foreach([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $days => $label)
                        <button type="button" wire:click="applyPreset({{ $days }})"
                                class="pill {{ $preset === $days ? 'pill--blue' : '' }}"
                                @if($preset === $days) aria-pressed="true" @endif>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <p class="mt-3 text-sm text-slate-500">
            Menampilkan {{ $r['range']['from'] }} s.d. {{ $r['range']['to'] }}
            ({{ $r['range']['weekdays'] }} hari kerja, zona {{ $r['range']['timezone'] === 'Asia/Jakarta' ? 'WIB' : $r['range']['timezone'] }}).
            Kapasitas dihitung dari jam kerja
            {{ sprintf('%02d:00', \App\Services\RoomUtilizationReport::BUSINESS_START_HOUR) }}–{{ sprintf('%02d:00', \App\Services\RoomUtilizationReport::BUSINESS_END_HOUR) }}.
        </p>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-bpjs.stat eyebrow="Utilisasi rata-rata" :value="$r['summary']['utilization'].'%'"
            sub="{{ $r['summary']['booked_hours'] }} dari {{ $r['summary']['capacity_hours'] }} jam"
            icon="dashboard" :tone="$r['summary']['utilization'] >= 60 ? 'green' : ($r['summary']['utilization'] >= 30 ? 'blue' : 'amber')" />
        <x-bpjs.stat eyebrow="Reservasi aktif" :value="$r['summary']['active_bookings']"
            sub="dari {{ $r['summary']['total_bookings'] }} total" icon="calendar" tone="blue" />
        <x-bpjs.stat eyebrow="Ruang terpakai" :value="$r['summary']['rooms_with_activity']"
            sub="ruangan dengan reservasi" icon="building" tone="slate" />
        <x-bpjs.stat eyebrow="Tingkat pembatalan" :value="$r['summary']['cancellation_rate'].'%'"
            sub="{{ $r['summary']['cancelled'] }} reservasi dibatalkan" icon="x"
            :tone="$r['summary']['cancellation_rate'] >= 20 ? 'red' : 'slate'" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Per-room utilization --}}
        <div class="lg:col-span-2">
            <x-bpjs.card title="Utilisasi per Ruangan">
                @if(count($r['rooms']) === 0)
                    <p class="text-sm text-slate-500 py-6 text-center">Tidak ada reservasi pada rentang ini.</p>
                @else
                    <table class="dtable">
                        <thead>
                            <tr>
                                <th>Ruangan</th>
                                <th class="text-right">Reservasi</th>
                                <th class="text-right">Jam terpakai</th>
                                <th style="width: 34%;">Utilisasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($r['rooms'] as $room)
                                @php($pct = min(100, $room['utilization']))
                                <tr>
                                    <td>
                                        <span class="font-semibold text-slate-900">{{ $room['name'] }}</span>
                                        <span class="block font-mono text-xs text-slate-400">{{ $room['code'] }}</span>
                                    </td>
                                    <td class="text-right font-mono text-slate-700">{{ $room['bookings'] }}</td>
                                    <td class="text-right font-mono text-slate-700">{{ $room['booked_hours'] }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full {{ $room['utilization'] >= 60 ? 'bg-green-500' : ($room['utilization'] >= 30 ? 'bg-blue-500' : 'bg-amber-400') }}"
                                                     style="width: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="font-mono text-xs text-slate-600 w-12 text-right">{{ $room['utilization'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-bpjs.card>

            {{-- Peak hours --}}
            <div class="mt-6">
                <x-bpjs.card title="Jam Sibuk (okupansi per jam)">
                    @php($maxHour = collect($r['peak_hours'])->max('hours') ?: 1)
                    <div class="flex items-end gap-1 h-40 mt-2">
                        @foreach($r['peak_hours'] as $bin)
                            @php($isBusiness = $bin['hour'] >= \App\Services\RoomUtilizationReport::BUSINESS_START_HOUR && $bin['hour'] < \App\Services\RoomUtilizationReport::BUSINESS_END_HOUR)
                            <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $bin['label'] }} — {{ $bin['hours'] }} jam">
                                <div class="w-full rounded-t {{ $isBusiness ? 'bg-blue-500' : 'bg-slate-200' }}"
                                     style="height: {{ $maxHour > 0 ? round($bin['hours'] / $maxHour * 100, 1) : 0 }}%; min-height: {{ $bin['hours'] > 0 ? '2px' : '0' }};"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-2 text-[10px] font-mono text-slate-400">
                        <span>00</span><span>06</span><span>12</span><span>18</span><span>23</span>
                    </div>
                </x-bpjs.card>
            </div>
        </div>

        {{-- Per-unit demand --}}
        <div>
            <x-bpjs.card title="Permintaan per Unit">
                @if(count($r['units']) === 0)
                    <p class="text-sm text-slate-500 py-6 text-center">Tidak ada data.</p>
                @else
                    @php($maxUnitHours = collect($r['units'])->max('booked_hours') ?: 1)
                    <ul class="space-y-3">
                        @foreach($r['units'] as $unit)
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-800">{{ $unit['name'] }}</span>
                                    <span class="font-mono text-slate-500">{{ $unit['booked_hours'] }} jam · {{ $unit['bookings'] }}x</span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-blue-500"
                                         style="width: {{ $maxUnitHours > 0 ? round($unit['booked_hours'] / $maxUnitHours * 100, 1) : 0 }}%;"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-bpjs.card>
        </div>
    </div>
</div>
