<div>
    @if(session('hours_status'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">{{ session('hours_status') }}</div>
    @endif

    <form wire:submit="save" class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hari</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Buka</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Tutup</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($dayLabels as $day => $label)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $label }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="isClosed.{{ $day }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="ml-2 text-sm text-gray-600">Tutup</span>
                            </label>
                        </td>
                        @if($isClosed[$day] ?? false)
                            <td class="px-6 py-4 text-sm text-gray-400" colspan="2">Tutup sepanjang hari</td>
                        @else
                            <td class="px-6 py-4">
                                <input type="time" wire:model="openTime.{{ $day }}" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                @error('openTime.'.$day) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-6 py-4">
                                <input type="time" wire:model="closeTime.{{ $day }}" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                @error('closeTime.'.$day) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @hasPermission('rooms.update')
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">Simpan Jam Operasional</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </button>
            </div>
        @endhasPermission
    </form>
</div>
