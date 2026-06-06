<div class="py-2">
    @php($r = $this->report)

    {{-- Filter bar --}}
    <div class="card card--pad mb-6 bpjs-rise">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="from" class="field__lbl">{{ __('Dari tanggal') }}</label>
                <input type="date" id="from" wire:model.live="from" class="input" max="{{ $to }}" />
            </div>
            <div>
                <label for="to" class="field__lbl">{{ __('Sampai tanggal') }}</label>
                <input type="date" id="to" wire:model.live="to" class="input" min="{{ $from }}" />
            </div>
            <div class="md:col-span-2">
                <span class="field__lbl">{{ __('Rentang cepat') }}</span>
                <div class="flex flex-wrap gap-2">
                    @foreach([7 => __('7 hari'), 30 => __('30 hari'), 90 => __('90 hari')] as $days => $label)
                        <button type="button" wire:click="applyPreset({{ $days }})"
                                class="pill {{ $preset === $days ? 'pill--blue' : '' }}"
                                @if($preset === $days) aria-pressed="true" @endif>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        @php($tzLabel = $r['range']['timezone'] === 'Asia/Jakarta' ? 'WIB' : $r['range']['timezone'])
        @php($bStart = sprintf('%02d:00', \App\Services\RoomUtilizationReport::BUSINESS_START_HOUR))
        @php($bEnd = sprintf('%02d:00', \App\Services\RoomUtilizationReport::BUSINESS_END_HOUR))
        <p class="mt-3 text-sm text-slate-500">
            {{ __('Menampilkan :from s.d. :to (:weekdays hari kerja, zona :tz). Kapasitas dihitung dari jam kerja :start–:end.', ['from' => $r['range']['from'], 'to' => $r['range']['to'], 'weekdays' => $r['range']['weekdays'], 'tz' => $tzLabel, 'start' => $bStart, 'end' => $bEnd]) }}
        </p>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <x-bpjs.stat :eyebrow="__('Utilisasi rata-rata')" :value="$r['summary']['utilization'].'%'"
            :sub="__(':booked dari :cap jam', ['booked' => $r['summary']['booked_hours'], 'cap' => $r['summary']['capacity_hours']])"
            icon="dashboard" :tone="$r['summary']['utilization'] >= 60 ? 'green' : ($r['summary']['utilization'] >= 30 ? 'blue' : 'amber')" />
        <x-bpjs.stat :eyebrow="__('Reservasi aktif')" :value="$r['summary']['active_bookings']"
            :sub="__('dari :total total', ['total' => $r['summary']['total_bookings']])" icon="calendar" tone="blue" />
        <x-bpjs.stat :eyebrow="__('Ruang terpakai')" :value="$r['summary']['rooms_with_activity']"
            :sub="__('ruangan dengan reservasi')" icon="building" tone="slate" />
        <x-bpjs.stat :eyebrow="__('Tingkat pembatalan')" :value="$r['summary']['cancellation_rate'].'%'"
            :sub="__(':n reservasi dibatalkan', ['n' => $r['summary']['cancelled']])" icon="x"
            :tone="$r['summary']['cancellation_rate'] >= 20 ? 'red' : 'slate'" />
        <x-bpjs.stat :eyebrow="__('Tingkat no-show')" :value="$r['summary']['no_show_rate'].'%'"
            :sub="__(':n dilepas · :u tak terklaim', ['n' => $r['summary']['no_show'], 'u' => $r['summary']['no_show_unreclaimed']])"
            icon="alert" :tone="$r['summary']['no_show_rate'] >= 15 ? 'red' : ($r['summary']['no_show_rate'] >= 5 ? 'amber' : 'slate')" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Per-room utilization --}}
        <div class="lg:col-span-2">
            <x-bpjs.card :title="__('Utilisasi per Ruangan')">
                @if(count($r['rooms']) === 0)
                    <p class="text-sm text-slate-500 py-6 text-center">{{ __('Tidak ada reservasi pada rentang ini.') }}</p>
                @else
                    <table class="dtable">
                        <thead>
                            <tr>
                                <th>{{ __('Ruangan') }}</th>
                                <th class="text-right">{{ __('Reservasi') }}</th>
                                <th class="text-right">{{ __('Jam terpakai') }}</th>
                                <th style="width: 34%;">{{ __('Utilisasi') }}</th>
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
                <x-bpjs.card :title="__('Jam Sibuk (okupansi per jam)')">
                    @php($maxHour = collect($r['peak_hours'])->max('hours') ?: 1)
                    <div class="flex items-end gap-1 h-40 mt-2">
                        @foreach($r['peak_hours'] as $bin)
                            @php($isBusiness = $bin['hour'] >= \App\Services\RoomUtilizationReport::BUSINESS_START_HOUR && $bin['hour'] < \App\Services\RoomUtilizationReport::BUSINESS_END_HOUR)
                            <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ __(':label — :hours jam', ['label' => $bin['label'], 'hours' => $bin['hours']]) }}">
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
            <x-bpjs.card :title="__('Permintaan per Unit')">
                @if(count($r['units']) === 0)
                    <p class="text-sm text-slate-500 py-6 text-center">{{ __('Tidak ada data.') }}</p>
                @else
                    @php($maxUnitHours = collect($r['units'])->max('booked_hours') ?: 1)
                    <ul class="space-y-3">
                        @foreach($r['units'] as $unit)
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-800">{{ $unit['name'] }}</span>
                                    <span class="font-mono text-slate-500">{{ __(':hours jam · :n x', ['hours' => $unit['booked_hours'], 'n' => $unit['bookings']]) }}</span>
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
