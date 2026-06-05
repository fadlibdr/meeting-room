<div>
    @if(session('facility_status'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-bpjs-green-200 bg-bpjs-green-50 px-4 py-3 text-sm text-bpjs-green-800">
            <x-icon name="checkCircle" :size="18" />
            <span>{{ session('facility_status') }}</span>
        </div>
    @endif

    @hasPermission('rooms.update')
        <div class="card card--pad mb-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-5">
                    <x-bpjs.field label="Fasilitas" for="selectedFacilityId" :error="$errors->first('selectedFacilityId')">
                        <select wire:model="selectedFacilityId" id="selectedFacilityId"
                                class="select @error('selectedFacilityId') input--err @enderror">
                            <option value="">— Pilih fasilitas —</option>
                            @foreach($availableFacilities as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}@if($f->category) ({{ ucfirst($f->category) }})@endif</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>
                </div>
                <div class="md:col-span-2">
                    <x-bpjs.field label="Jumlah" for="quantity" :error="$errors->first('quantity')">
                        <input wire:model="quantity" type="number" min="1" id="quantity"
                               class="input @error('quantity') input--err @enderror" />
                    </x-bpjs.field>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center cursor-pointer gap-2 pb-2.5">
                        <input type="checkbox" wire:model="isOperational"
                               class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                        <span class="text-sm text-slate-700">Siap pakai</span>
                    </label>
                </div>
                <div class="md:col-span-3">
                    <x-bpjs.button wire:click="addFacility" type="button" icon="plus" block>Tambah</x-bpjs.button>
                </div>
            </div>
            <div class="mt-3">
                <input wire:model="notes" type="text" placeholder="Catatan (opsional)"
                       class="input @error('notes') input--err @enderror" />
                @error('notes') <p class="field__err">{{ $message }}</p> @enderror
            </div>
        </div>
    @endhasPermission

    <div class="card">
        <table class="dtable">
            <thead>
                <tr>
                    <th>Fasilitas</th>
                    <th>Jumlah</th>
                    <th>Kondisi</th>
                    <th>Catatan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        @if($editingItemId === $item->id)
                            <td class="font-semibold text-slate-900">{{ $item->facility->name }}</td>
                            <td>
                                <input wire:model="editQuantity" type="number" min="1"
                                       class="input @error('editQuantity') input--err @enderror" style="width: 90px;" />
                                @error('editQuantity') <p class="field__err">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <label class="flex items-center cursor-pointer gap-2">
                                    <input type="checkbox" wire:model="editIsOperational"
                                           class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                                    <span class="text-xs text-slate-600">Siap pakai</span>
                                </label>
                            </td>
                            <td>
                                <input wire:model="editNotes" type="text" class="input" />
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <button wire:click="saveEdit" type="button"
                                            class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">Simpan</button>
                                    <button wire:click="cancelEdit" type="button"
                                            class="text-sm font-semibold text-slate-500 hover:text-slate-800">Batal</button>
                                </div>
                            </td>
                        @else
                            <td class="font-semibold text-slate-900">{{ $item->facility->name }}</td>
                            <td class="font-mono text-slate-700">{{ $item->quantity }}</td>
                            <td>
                                @if($item->is_operational)
                                    <x-bpjs.pill variant="green">Siap pakai</x-bpjs.pill>
                                @else
                                    <x-bpjs.pill variant="amber">Rusak</x-bpjs.pill>
                                @endif
                            </td>
                            <td class="text-slate-500">{{ $item->notes ?? '-' }}</td>
                            <td class="text-right">
                                @hasPermission('rooms.update')
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <button wire:click="startEdit({{ $item->id }})" type="button"
                                                class="text-sm font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">Edit</button>
                                        <button wire:click="remove({{ $item->id }})" wire:confirm="Hapus {{ $item->facility->name }} dari ruang?" type="button"
                                                class="text-sm font-semibold text-red-700 hover:text-red-800">Hapus</button>
                                    </div>
                                @endhasPermission
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-500" style="padding: 32px 16px;">
                            Belum ada fasilitas yang ditambahkan ke ruang ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
