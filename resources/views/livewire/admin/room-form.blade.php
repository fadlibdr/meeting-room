<div>
    <form wire:submit="save">
        <div class="card bpjs-rise">
            <div class="card--pad space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-bpjs.field :label="__('Kode Ruang')" req for="code" :error="$errors->first('code')">
                        <input wire:model="code" type="text" id="code" placeholder="RM-A01"
                               class="input @error('code') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Nama Ruang')" req for="name" :error="$errors->first('name')">
                        <input wire:model="name" type="text" id="name" placeholder="Ruang Garuda 1"
                               class="input @error('name') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Lokasi / Gedung')" for="location" :error="$errors->first('location')">
                        <input wire:model="location" type="text" id="location"
                               class="input @error('location') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Lantai')" for="floor" :error="$errors->first('floor')">
                        <input wire:model="floor" type="text" id="floor" placeholder="Lantai 3"
                               class="input @error('floor') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Kapasitas')" req for="capacity" :error="$errors->first('capacity')">
                        <input wire:model="capacity" type="number" min="1" id="capacity"
                               class="input @error('capacity') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Buffer Setelah Rapat (menit)')" for="bookingBufferMinutes" :error="$errors->first('bookingBufferMinutes')">
                        <input wire:model="bookingBufferMinutes" type="number" min="0" id="bookingBufferMinutes"
                               class="input @error('bookingBufferMinutes') input--err @enderror" />
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Status')" req for="status" :error="$errors->first('status')">
                        <select wire:model="status" id="status" class="select @error('status') input--err @enderror">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Mode Approval')" req for="approvalMode"
                                  :error="$errors->first('approvalMode')"
                                  :hint="__('Mengubah mode tidak memengaruhi booking yang sedang berjalan (snapshot saat submit).')">
                        <select wire:model="approvalMode" id="approvalMode" class="select @error('approvalMode') input--err @enderror">
                            @foreach($approvalModes as $m)
                                <option value="{{ $m->value }}">{{ $m->label() }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>

                    <x-bpjs.field :label="__('Kebijakan Persetujuan')" for="approvalPolicyId"
                                  :hint="__('Opsional. Jika dipilih, menggantikan Mode Approval dengan rantai multi-langkah.')"
                                  :error="$errors->first('approvalPolicyId')">
                        <select wire:model="approvalPolicyId" id="approvalPolicyId" class="select @error('approvalPolicyId') input--err @enderror">
                            <option value="">{{ __('— Gunakan Mode Approval —') }}</option>
                            @foreach($approvalPolicies as $policy)
                                <option value="{{ $policy->id }}">{{ $policy->name }}</option>
                            @endforeach
                        </select>
                    </x-bpjs.field>
                </div>

                <x-bpjs.field :label="__('Deskripsi')" for="description" :error="$errors->first('description')">
                    <textarea wire:model="description" id="description" rows="3"
                              class="textarea @error('description') input--err @enderror"></textarea>
                </x-bpjs.field>

                <x-bpjs.field :label="__('Foto Ruang')" for="photo"
                              :hint="__('Opsional. JPG, PNG, atau WEBP, maksimal 4 MB.')"
                              :error="$errors->first('photo')">
                    <div class="space-y-3">
                        {{-- Preview: a newly selected upload takes precedence over the stored one --}}
                        @if($photo && $photo->isPreviewable())
                            <div class="flex items-start gap-3">
                                <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('Pratinjau') }}"
                                     class="h-28 w-40 rounded-lg border border-slate-200 object-cover" />
                                <button type="button" wire:click="clearPhoto" class="btn btn--ghost">{{ __('Batalkan pilihan') }}</button>
                            </div>
                        @elseif($existingPhotoPath && ! $removePhoto)
                            <div class="flex items-start gap-3">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingPhotoPath) }}"
                                     alt="{{ $name }}" class="h-28 w-40 rounded-lg border border-slate-200 object-cover" />
                                <label class="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" wire:model.live="removePhoto" class="checkbox" />
                                    {{ __('Hapus foto') }}
                                </label>
                            </div>
                        @endif

                        <input type="file" id="photo" wire:model="photo"
                               accept="image/jpeg,image/png,image/webp"
                               class="input @error('photo') input--err @enderror" />
                        <div wire:loading wire:target="photo" class="text-sm text-slate-500">{{ __('Mengunggah…') }}</div>
                    </div>
                </x-bpjs.field>
            </div>

            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button variant="ghost" :href="route('admin.rooms.index')" wire:navigate>{{ __('Batal') }}</x-bpjs.button>
                <x-bpjs.button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? __('Simpan Perubahan') : __('Buat Ruang') }}</span>
                    <span wire:loading wire:target="save">{{ __('Memproses...') }}</span>
                </x-bpjs.button>
            </div>
        </div>
    </form>
</div>
