<div class="py-2" style="max-width: 760px;">
    @if($plainTextToken)
        <div class="card card--pad bpjs-rise mb-5" style="border-color: var(--amber-200); background: var(--amber-50);">
            <div class="flex items-start gap-3">
                <span style="color: var(--amber-700); margin-top: 1px;"><x-icon name="info" :size="20" /></span>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold" style="color: var(--amber-800);">{{ __('Token dibuat — salin sekarang') }}</h3>
                    <p class="mt-1 text-sm" style="color: var(--amber-700);">{{ __('Token hanya ditampilkan sekali. Simpan di tempat aman.') }}</p>
                    <code class="mono mt-3 block select-all" style="background: #fff; border: 1px solid var(--amber-200); border-radius: 8px; padding: 10px 12px; font-size: 12.5px; color: var(--slate-900); word-break: break-all;">{{ $plainTextToken }}</code>
                    <button type="button" wire:click="dismissToken" class="mt-3 text-xs font-semibold text-slate-500 hover:text-slate-800">{{ __('Saya sudah menyalin') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Create --}}
    <form wire:submit="createToken" class="card bpjs-rise mb-6">
        <div class="card--pad space-y-4">
            <x-bpjs.field :label="__('Nama Token')" req for="tname" :error="$errors->first('name')">
                <input wire:model="name" type="text" id="tname" placeholder="{{ __('cth. Integrasi Portal') }}"
                       class="input @error('name') input--err @enderror" />
            </x-bpjs.field>
            <x-bpjs.field :label="__('Kemampuan')" :error="$errors->first('abilities')">
                <div class="space-y-2">
                    @foreach($allAbilities as $ability)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="abilities" value="{{ $ability->value }}"
                                   class="rounded border-slate-300 text-bpjs-blue-600 shadow-sm focus:ring-bpjs-blue-500" />
                            <span class="text-sm text-slate-700">{{ $ability->label() }}</span>
                            <span class="mono text-xs text-slate-400">{{ $ability->value }}</span>
                        </label>
                    @endforeach
                </div>
            </x-bpjs.field>
        </div>
        <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
            <x-bpjs.button type="submit" icon="plus">{{ __('Buat Token') }}</x-bpjs.button>
        </div>
    </form>

    {{-- List --}}
    <div class="card bpjs-rise">
        <table class="dtable">
            <thead>
                <tr>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Kemampuan') }}</th>
                    <th>{{ __('Terakhir Dipakai') }}</th>
                    <th class="text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tokens as $token)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $token->name }}</td>
                        <td>
                            @foreach((array) $token->abilities as $ability)
                                <span class="pill pill--slate font-mono" style="font-size: 11px;">{{ $ability }}</span>
                            @endforeach
                        </td>
                        <td class="text-slate-500" style="font-size: 12.5px;">
                            {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : __('Belum pernah') }}
                        </td>
                        <td class="text-right">
                            <button wire:click="revoke({{ $token->id }})" wire:confirm="{{ __('Cabut token ini?') }}" type="button"
                                    class="text-sm font-semibold text-red-700 hover:text-red-800">{{ __('Cabut') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500" style="padding: 40px 16px;">{{ __('Belum ada token.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
