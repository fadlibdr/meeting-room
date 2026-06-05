<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-[18px] py-2.5 bg-bpjs-blue-600 border border-transparent rounded-ctl font-semibold text-[13.5px] text-white shadow-[0_1px_2px_rgba(0,65,109,.25)] hover:bg-bpjs-blue-500 focus:bg-bpjs-blue-500 active:bg-bpjs-blue-700 focus:outline-none focus-bpjs disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
