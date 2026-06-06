<div>
    @if(session('hours_status'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800">
            <x-icon name="checkCircle" :size="18" />
            <span>{{ session('hours_status') }}</span>
        </div>
    @endif

    <form wire:submit="save">
        <div class="card">
            <table class="dtable">
                <thead>
                    <tr>
                        <th>{{ __('Hari') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Jam Buka') }}</th>
                        <th>{{ __('Jam Tutup') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dayLabels as $day => $label)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $label }}</td>
                            <td>
                                <label class="flex items-center cursor-pointer gap-2">
                                    <input type="checkbox" wire:model.live="isClosed.{{ $day }}"
                                           class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                                    <span class="text-sm text-slate-600">{{ __('Tutup') }}</span>
                                </label>
                            </td>
                            @if($isClosed[$day] ?? false)
                                <td class="text-slate-400" colspan="2">{{ __('Tutup sepanjang hari') }}</td>
                            @else
                                <td>
                                    <input type="time" wire:model="openTime.{{ $day }}"
                                           class="input @error('openTime.'.$day) input--err @enderror" style="width: 150px;" />
                                    @error('openTime.'.$day) <p class="field__err">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="time" wire:model="closeTime.{{ $day }}"
                                           class="input @error('closeTime.'.$day) input--err @enderror" style="width: 150px;" />
                                    @error('closeTime.'.$day) <p class="field__err">{{ $message }}</p> @enderror
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @hasPermission('rooms.update')
                <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                    <x-bpjs.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ __('Simpan Jam Operasional') }}</span>
                        <span wire:loading wire:target="save">{{ __('Memproses...') }}</span>
                    </x-bpjs.button>
                </div>
            @endhasPermission
        </div>
    </form>
</div>
