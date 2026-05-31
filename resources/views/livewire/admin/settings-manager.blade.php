<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Page header --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-gray-900">Pengaturan Sistem</h1>
            <p class="mt-1 text-sm text-gray-600">
                Konfigurasi runtime aplikasi. Perubahan berlaku seketika setelah disimpan.
            </p>
        </div>

        {{-- Flash messages --}}
        @if($successMessage)
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4">
                {{ $successMessage }}
            </div>
        @endif

        @if($errorMessage)
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Settings grouped by section --}}
        @forelse($groupedSettings as $group => $settings)
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">
                        {{ ucfirst($group) }}
                    </h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($settings as $setting)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ $setting->label }}
                                        </h3>
                                        @if(!$setting->is_editable)
                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                Sistem
                                            </span>
                                        @endif
                                    </div>

                                    @if($setting->description)
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ $setting->description }}
                                        </p>
                                    @endif

                                    {{-- Edit mode for this row --}}
                                    @if($editingId === $setting->id)
                                        <div class="mt-3 space-y-2">
                                            @if($setting->data_type === 'integer')
                                                <input
                                                    type="number"
                                                    wire:model="editValue"
                                                    min="0"
                                                    class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                            @elseif($setting->data_type === 'boolean')
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="editValue"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    />
                                                    <span class="ml-2 text-sm text-gray-600">Aktif</span>
                                                </label>
                                            @else
                                                <input
                                                    type="text"
                                                    wire:model="editValue"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                            @endif

                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="save"
                                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                >
                                                    Simpan
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="cancelEdit"
                                                    class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                                >
                                                    Batal
                                                </button>
                                           </div>
                                          <livewire:admin.backup-manager />
                                        </div>
                                    @else
                                        <div class="mt-2 text-sm">
                                            <span class="font-medium text-gray-700">Nilai saat ini:</span>
                                            @if($setting->data_type === 'boolean')
                                                @if($setting->getCastedValue())
                                                    <span class="ml-1 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Aktif</span>
                                                @else
                                                    <span class="ml-1 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                                                @endif
                                            @else
                                                <span class="ml-1 font-mono text-gray-900">{{ $setting->value }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Last updated audit info --}}
                                    @if($setting->updated_at && $setting->updated_by_user_id)
                                        <p class="mt-2 text-xs text-gray-400">
                                            Diperbarui {{ $setting->updated_at->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Edit button (hidden when this row is being edited or read-only) --}}
                                @if($editingId !== $setting->id)
                                    @if($setting->is_editable)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $setting->id }})"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                        >
                                            Edit
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Tidak dapat diedit</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-sm text-gray-500">
                Belum ada pengaturan yang tersedia.
            </div>
        @endforelse
    </div>
</div>
