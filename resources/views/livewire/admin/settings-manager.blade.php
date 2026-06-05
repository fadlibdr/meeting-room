<div class="space-y-6">

    {{-- Flash messages --}}
    @if($successMessage)
        <div class="card card--pad bpjs-rise" style="border-color: var(--bpjs-green-200); background: var(--bpjs-green-50);">
            <div class="flex items-center gap-2" style="color: var(--bpjs-green-800); font-size: 13.5px; font-weight: 600;">
                <x-icon name="checkCircle" :size="18" />
                {{ $successMessage }}
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="card card--pad bpjs-rise" style="border-color: var(--red-300); background: var(--red-50);">
            <div class="flex items-center gap-2" style="color: var(--red-800); font-size: 13.5px; font-weight: 600;">
                <x-icon name="alert" :size="18" />
                {{ $errorMessage }}
            </div>
        </div>
    @endif

    {{-- Settings grouped by section --}}
    @forelse($groupedSettings as $group => $settings)
        <x-bpjs.card :title="ucfirst($group)" rise>
            <div style="display: flex; flex-direction: column;">
                @foreach($settings as $setting)
                    <div class="flex items-start justify-between gap-4" style="padding: 16px 0; border-top: 1px solid var(--slate-100);">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 style="font-size: 13.5px; font-weight: 600; color: var(--slate-800);">
                                    {{ $setting->label }}
                                </h3>
                                @if(!$setting->is_editable)
                                    <x-bpjs.pill variant="slate">Sistem</x-bpjs.pill>
                                @endif
                            </div>

                            @if($setting->description)
                                <p style="font-size: 12px; color: var(--slate-500); margin-top: 2px;">
                                    {{ $setting->description }}
                                </p>
                            @endif

                            {{-- Edit mode for this row --}}
                            @if($editingId === $setting->id)
                                <div class="mt-3 space-y-3">
                                    @if($setting->data_type === 'integer')
                                        <input
                                            type="number"
                                            wire:model="editValue"
                                            min="0"
                                            class="input"
                                            style="max-width: 160px;"
                                        />
                                    @elseif($setting->data_type === 'boolean')
                                        <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                                            <span class="relative inline-flex" style="width: 44px; height: 24px;">
                                                <input
                                                    type="checkbox"
                                                    wire:model="editValue"
                                                    class="peer sr-only"
                                                />
                                                <span aria-hidden="true" style="position: absolute; inset: 0; border-radius: 9999px; background: var(--slate-300); transition: background .15s;" class="peer-checked:!bg-[var(--bpjs-green-500)] peer-focus-visible:[box-shadow:var(--bpjs-ring)]"></span>
                                                <span aria-hidden="true" style="position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.2); transition: transform .15s;" class="peer-checked:translate-x-5"></span>
                                            </span>
                                            <span style="font-size: 13.5px; font-weight: 500; color: var(--slate-700);">Aktif</span>
                                        </label>
                                    @else
                                        <input
                                            type="text"
                                            wire:model="editValue"
                                            class="input"
                                        />
                                    @endif

                                    <div class="flex gap-2">
                                        <x-bpjs.button variant="primary" icon="check" wire:click="save">
                                            Simpan
                                        </x-bpjs.button>
                                        <x-bpjs.button variant="ghost" wire:click="cancelEdit">
                                            Batal
                                        </x-bpjs.button>
                                    </div>
                                </div>
                            @else
                                <div class="mt-2 flex items-center gap-2" style="font-size: 13px;">
                                    <span style="font-weight: 600; color: var(--slate-600);">Nilai saat ini:</span>
                                    @if($setting->data_type === 'boolean')
                                        @if($setting->getCastedValue())
                                            <x-bpjs.pill variant="green">Aktif</x-bpjs.pill>
                                        @else
                                            <x-bpjs.pill variant="slate">Nonaktif</x-bpjs.pill>
                                        @endif
                                    @else
                                        <span class="font-mono" style="color: var(--slate-900);">{{ $setting->value }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Last updated audit info --}}
                            @if($setting->updated_at && $setting->updated_by_user_id)
                                <p class="mt-2 flex items-center gap-1.5" style="font-size: 11.5px; color: var(--slate-400);">
                                    <x-icon name="clock" :size="13" />
                                    Diperbarui {{ $setting->updated_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>

                        {{-- Edit button (hidden when this row is being edited or read-only) --}}
                        @if($editingId !== $setting->id)
                            @if($setting->is_editable)
                                <x-bpjs.button variant="ghost" icon="settings" wire:click="startEdit({{ $setting->id }})">
                                    Edit
                                </x-bpjs.button>
                            @else
                                <span class="italic" style="font-size: 11.5px; color: var(--slate-400);">Tidak dapat diedit</span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </x-bpjs.card>
    @empty
        <x-bpjs.card>
            <div class="text-center" style="padding: 24px; font-size: 13.5px; color: var(--slate-500);">
                Belum ada pengaturan yang tersedia.
            </div>
        </x-bpjs.card>
    @endforelse
</div>
