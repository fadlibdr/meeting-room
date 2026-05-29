<div>
    <form wire:submit="save" class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="roomId" class="block text-sm font-medium text-gray-700">Ruang <span class="text-red-500">*</span></label>
                    <select wire:model.live="roomId" id="roomId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">— Pilih ruang —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->name }}</option>
                        @endforeach
                    </select>
                    @error('roomId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="blockType" class="block text-sm font-medium text-gray-700">Jenis Blokir <span class="text-red-500">*</span></label>
                    <select wire:model="blockType" id="blockType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach($blockTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('blockType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Judul <span class="text-red-500">*</span></label>
                <input wire:model="title" type="text" id="title" placeholder="contoh: Pemeliharaan AC" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="startsAt" class="block text-sm font-medium text-gray-700">Mulai <span class="text-red-500">*</span></label>
                    <input wire:model.live="startsAt" type="datetime-local" id="startsAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('startsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="endsAt" class="block text-sm font-medium text-gray-700">Selesai <span class="text-red-500">*</span></label>
                    <input wire:model.live="endsAt" type="datetime-local" id="endsAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('endsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700">Alasan (opsional)</label>
                <textarea wire:model="reason" id="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if($conflicts->isNotEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-800">Booking yang bentrok ({{ $conflicts->count() }}):</p>
                    <ul class="mt-2 space-y-1 text-sm text-amber-700">
                        @foreach($conflicts as $b)
                            <li>• {{ $b->subject }} ({{ $b->starts_at->format('d M H:i') }}–{{ $b->ends_at->format('H:i') }})</li>
                        @endforeach
                    </ul>
                    <label class="mt-3 flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="cancelConflicting" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500" />
                        <span class="ml-2 text-sm text-amber-900">Batalkan booking di atas dan tetap buat blokir</span>
                    </label>
                </div>
            @endif

            @error('conflict') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
            <a href="{{ route('admin.room-blocks.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md">Batal</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                <span wire:loading.remove wire:target="save">Simpan Blokir</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </button>
        </div>
    </form>
</div>
