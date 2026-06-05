<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 px-[18px] py-2.5 bg-white border border-slate-300 rounded-ctl font-semibold text-[13.5px] text-slate-700 hover:bg-slate-50 focus:outline-none focus-bpjs disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
