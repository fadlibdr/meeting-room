@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full bg-white text-slate-900 placeholder:text-slate-400 border border-slate-300 rounded-ctl px-3 py-2.5 transition focus:border-bpjs-blue-500 focus:outline-none focus:ring-0 focus-bpjs disabled:opacity-50 disabled:cursor-not-allowed']) }}>
