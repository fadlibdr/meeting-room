<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-[18px] py-2.5 bg-red-600 border border-transparent rounded-ctl font-semibold text-[13.5px] text-white hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500/40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
