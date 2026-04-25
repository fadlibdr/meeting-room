<div>
    {{-- Generated Password Banner (shown once after create) --}}
    @if($generatedPassword)
        <div class="mb-6 bg-yellow-50 border border-yellow-300 rounded-lg p-4">
            <div class="flex items-start">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-yellow-800">
                        Pengguna berhasil dibuat — catat password sementara
                    </h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        Password ini hanya ditampilkan sekali. Bagikan ke pengguna melalui kanal aman.
                    </p>
                    <div class="mt-3 inline-block bg-white border border-yellow-200 rounded px-4 py-2 font-mono text-lg select-all">
                        {{ $generatedPassword }}
                    </div>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <a href="{{ route('admin.users.index') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                    Kembali ke daftar pengguna
                </a>
                <a href="{{ route('admin.users.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md">
                    Tambah pengguna lagi
                </a>
            </div>
        </div>
    @endif

    {{-- Form (hidden if password just generated; that case shows banner only) --}}
    @if(!$generatedPassword)
        <form wire:submit="save" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 space-y-6">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="name"
                        type="text"
                        id="name"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="email"
                        type="email"
                        id="email"
                        placeholder="contoh: nama.belakang@bpjs-kesehatan.go.id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Unit --}}
                <div>
                    <label for="unitId" class="block text-sm font-medium text-gray-700">
                        Unit <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="unitId"
                        id="unitId"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option value="">— Pilih unit —</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Roles --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <div class="space-y-2 border border-gray-200 rounded-md p-3">
                        @foreach($roles as $role)
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="roleIds"
                                    value="{{ $role->id }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700">{{ $role->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">({{ $role->code }})</span>
                            </label>
                        @endforeach
                    </div>
                    @error('roleIds')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Is Active --}}
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="isActive"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">Akun aktif</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">
                        Pengguna nonaktif tidak dapat login.
                    </p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md">
                    Batal
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $isEditMode ? 'Simpan Perubahan' : 'Buat Pengguna' }}
                    </span>
                    <span wire:loading wire:target="save">
                        Memproses...
                    </span>
                </button>
            </div>
        </form>
    @endif
</div>
