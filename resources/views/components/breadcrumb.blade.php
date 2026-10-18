@php
    $segments = request()->segments();
@endphp
<nav aria-label="breadcrumb" class="inline-flex items-center">
    <ol class="inline-flex items-center space-x-1.5 md:space-x-2 text-xs font-semibold py-1.5 px-3 rounded-xl bg-white/90 dark:bg-slate-800/80 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs backdrop-blur-sm m-0 list-none">
        <li class="inline-flex items-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-sky-600 dark:text-slate-400 dark:hover:text-cyan-400 transition-colors font-medium">
                <i class="bi bi-house-door-fill text-sky-500 dark:text-cyan-400 text-xs"></i>
                <span>Home</span>
            </a>
        </li>

        @foreach ($segments as $index => $segment)
            @if (strlen($segment) === 36 && str_contains($segment, '-'))
                @continue
            @endif
            @php
                $url = url(implode('/', array_slice($segments, 0, $index + 1)));

                $label = match ($segment) {
                    'create' => 'Tambah',
                    'edit' => 'Edit',
                    default => ucfirst(str_replace('-', ' ', $segment)),
                };
            @endphp

            <li class="inline-flex items-center">
                <i class="bi bi-chevron-right text-[10px] text-slate-300 dark:text-slate-600 mx-1"></i>
                @if ($loop->last)
                    <span class="text-slate-800 dark:text-slate-100 font-bold">
                        {{ $label }}
                    </span>
                @else
                    <a href="{{ $url }}" class="text-slate-500 hover:text-sky-600 dark:text-slate-400 dark:hover:text-cyan-400 transition-colors font-medium">
                        {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
