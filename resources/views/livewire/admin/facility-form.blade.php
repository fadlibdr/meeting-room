<div>
    <form wire:submit="save" class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Kode Fasilitas <span class="text-red-500">*</span></label>
                    <input wire:model="code" type="text" id="code" placeholder="projector"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nama Fasilitas <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" id="name" placeholder="Proyektor"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Kategori</label>
                    <select wire:model="category" id="category"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">— Tanpa kategori —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="icon" class="block text-sm font-medium text-gray-700">Ikon (opsional)</label>
                    <input wire:model="icon" type="text" id="icon" placeholder="nama-ikon"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('icon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <span class="ml-2 text-sm text-gray-700">Fasilitas aktif</span>
                </label>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
            <a href="{{ route('admin.facilities.index') }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md">Batal</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Simpan Perubahan' : 'Buat Fasilitas' }}</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </button>
        </div>
    </form>
</div>
