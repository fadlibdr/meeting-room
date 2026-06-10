<div class="py-2">
    @if($feedback)
        <div class="card card--pad bpjs-rise mb-4 flex items-center gap-2.5"
             style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="18" /></span>
            <span class="text-sm font-medium" style="color: var(--bpjs-green-800);">{{ $feedback }}</span>
        </div>
    @endif

    <form wire:submit="save">
        <x-bpjs.card :pad="false" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th rowspan="2" class="align-bottom">{{ __('Jenis Notifikasi') }}</th>
                            @foreach($channels as $channel)
                                <th colspan="2" class="text-center">{{ $channelLabels[$channel] ?? $channel }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($channels as $channel)
                                <th class="text-center text-[11px] font-medium text-slate-500">{{ __('Aktif') }}</th>
                                <th class="text-center text-[11px] font-medium text-slate-500">{{ __('Dpt. diubah') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr wire:key="ntype-{{ $type->value }}">
                                <td class="font-medium text-slate-800">{{ $type->label() }}</td>
                                @foreach($channels as $channel)
                                    <td class="text-center">
                                        <input type="checkbox" wire:model="matrix.{{ $type->value }}.{{ $channel }}.enabled"
                                               class="rounded border-slate-300 text-bpjs-blue-600 focus:ring-bpjs-blue-500" />
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" wire:model="matrix.{{ $type->value }}.{{ $channel }}.overridable"
                                               class="rounded border-slate-300 text-slate-400 focus:ring-bpjs-blue-500" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button type="submit">{{ __('Simpan') }}</x-bpjs.button>
            </div>
        </x-bpjs.card>
        <p class="mt-3 text-xs text-slate-500">
            {{ __('"Aktif" = saluran ini aktif secara default untuk semua pengguna. "Dpt. diubah" = pengguna boleh menyesuaikannya di preferensi mereka.') }}
        </p>
    </form>
</div>
