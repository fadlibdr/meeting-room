<div class="py-2 mx-auto" style="max-width: 760px;">
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
                            <th>{{ __('Jenis Notifikasi') }}</th>
                            @foreach($channels as $channel)
                                <th class="text-center">{{ $channelLabels[$channel] ?? $channel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr wire:key="pref-{{ $type->value }}">
                                <td class="font-medium text-slate-800">{{ $type->label() }}</td>
                                @foreach($channels as $channel)
                                    <td class="text-center">
                                        @if($state[$type->value][$channel]['overridable'] ?? false)
                                            <input type="checkbox" wire:model="state.{{ $type->value }}.{{ $channel }}.enabled"
                                                   class="rounded border-slate-300 text-bpjs-blue-600 focus:ring-bpjs-blue-500" />
                                        @else
                                            <span title="{{ __('Dikelola oleh administrator') }}" class="text-slate-400">
                                                @if($state[$type->value][$channel]['enabled'] ?? false)
                                                    <x-icon name="checkCircle" :size="16" />
                                                @else
                                                    —
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" type="button" wire:click="resetToDefault">{{ __('Kembalikan ke Default') }}</x-bpjs.button>
                <x-bpjs.button type="submit">{{ __('Simpan') }}</x-bpjs.button>
            </div>
        </x-bpjs.card>
        <p class="mt-3 text-xs text-slate-500">
            {{ __('Saluran berikon abu-abu dikelola oleh administrator dan tidak dapat diubah. Email juga mengikuti sakelar "Terima notifikasi email" di profil Anda.') }}
        </p>
    </form>
</div>
