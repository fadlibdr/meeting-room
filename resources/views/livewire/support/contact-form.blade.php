<div class="mx-auto" style="max-width: 640px;">
    @if($sent)
        <div class="card card--pad bpjs-rise text-center space-y-3">
            <span style="color: var(--bpjs-green-700);"><x-icon name="checkCircle" :size="32" /></span>
            <h2 class="text-lg font-bold text-slate-900">{{ __('Permintaan terkirim') }}</h2>
            <p class="text-sm text-slate-600">
                {{ __('Terima kasih. Tim kami akan menindaklanjuti. Nomor tiket Anda:') }}
                <span class="mono font-semibold">#{{ $ticketId }}</span>.
            </p>
            <button type="button" wire:click="$set('sent', false)" class="btn btn--ghost">
                {{ __('Kirim permintaan lain') }}
            </button>
        </div>
    @else
        <form wire:submit="submit" class="card bpjs-rise">
            <div class="card--pad space-y-4">
                <x-bpjs.field :label="__('Kategori')" req for="category" :error="$errors->first('category')">
                    <select wire:model="category" id="category" class="input">
                        @foreach($categories as $c)
                            <option value="{{ $c->value }}">{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </x-bpjs.field>

                <x-bpjs.field :label="__('Subjek (opsional)')" for="subject" :error="$errors->first('subject')">
                    <input wire:model="subject" type="text" id="subject" maxlength="150"
                           class="input @error('subject') input--err @enderror" />
                </x-bpjs.field>

                <x-bpjs.field :label="__('Pesan')" req for="message" :error="$errors->first('message')">
                    <textarea wire:model="message" id="message" rows="6"
                              class="input @error('message') input--err @enderror"
                              placeholder="{{ __('Jelaskan pertanyaan atau masalah Anda…') }}"></textarea>
                </x-bpjs.field>

                @if($helpCenterUrl)
                    <p class="text-sm text-slate-500">
                        {{ __('Butuh jawaban cepat? Kunjungi') }}
                        <a href="{{ $helpCenterUrl }}" target="_blank" rel="noopener"
                           class="font-semibold text-bpjs-blue-600 hover:text-bpjs-blue-700">{{ __('Pusat Bantuan') }}</a>.
                    </p>
                @endif
            </div>
            <div class="modal__foot" style="border-radius: 0 0 16px 16px;">
                <x-bpjs.button type="submit" size="lg">{{ __('Kirim') }}</x-bpjs.button>
            </div>
        </form>
    @endif
</div>
