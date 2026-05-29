<div>
    @if(session('facility_status'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">{{ session('facility_status') }}</div>
    @endif

    @hasPermission('rooms.update')
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-5">
                    <label for="selectedFacilityId" class="block text-sm font-medium text-gray-700 mb-1">Fasilitas</label>
                    <select wire:model="selectedFacilityId" id="selectedFacilityId"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">— Pilih fasilitas —</option>
                        @foreach($availableFacilities as $f)
                            <option value="{{ $f->id }}">{{ $f->name }}@if($f->category) ({{ ucfirst($f->category) }})@endif</option>
                        @endforeach
                    </select>
                    @error('selectedFacilityId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                    <input wire:model="quantity" type="number" min="1" id="quantity"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center cursor-pointer mt-6">
                        <input type="checkbox" wire:model="isOperational" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="ml-2 text-sm text-gray-700">Siap pakai</span>
                    </label>
                </div>
                <div class="md:col-span-3">
                    <button wire:click="addFacility" type="button"
                            class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">+ Tambah</button>
                </div>
            </div>
            <div class="mt-3">
                <input wire:model="notes" type="text" placeholder="Catatan (opsional)"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    @endhasPermission

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fasilitas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($items as $item)
                    <tr class="hover:bg-gray-50">
                        @if($editingItemId === $item->id)
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->facility->name }}</td>
                            <td class="px-6 py-4">
                                <input wire:model="editQuantity" type="number" min="1" class="w-20 rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('editQuantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-6 py-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="editIsOperational" class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                                    <span class="ml-2 text-xs text-gray-600">Siap pakai</span>
                                </label>
                            </td>
                            <td class="px-6 py-4">
                                <input wire:model="editNotes" type="text" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-3">
                                <button wire:click="saveEdit" type="button" class="text-indigo-600 hover:text-indigo-900">Simpan</button>
                                <button wire:click="cancelEdit" type="button" class="text-gray-500 hover:text-gray-700">Batal</button>
                            </td>
                        @else
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $item->facility->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->quantity }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item->is_operational)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Siap pakai</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Rusak</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $item->notes ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                @hasPermission('rooms.update')
                                    <button wire:click="startEdit({{ $item->id }})" type="button" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                    <button wire:click="remove({{ $item->id }})" wire:confirm="Hapus {{ $item->facility->name }} dari ruang?" type="button" class="text-red-600 hover:text-red-900">Hapus</button>
                                @endhasPermission
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada fasilitas yang ditambahkan ke ruang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
