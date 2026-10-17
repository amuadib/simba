@php
    $segments = request()->segments();
@endphp
<nav aria-label="breadcrumb" class="inline-flex items-center">
    <ol class="inline-flex items-center space-x-1 md:space-x-1.5 text-xs font-semibold p-1 px-2.5 rounded-xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 backdrop-blur-sm m-0 list-none">
        <li class="inline-flex items-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-sky-600 dark:text-slate-400 dark:hover:text-cyan-400 transition-colors">
                <i class="bi bi-house-door-fill text-sky-500 text-xs"></i>
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
                <i class="bi bi-chevron-right text-[10px] text-slate-400 dark:text-slate-500 mx-1"></i>
                @if ($loop->last)
                    <span class="text-sky-600 dark:text-cyan-400 font-bold">
                        {{ $label }}
                    </span>
                @else
                    <a href="{{ $url }}" class="text-slate-500 hover:text-sky-600 dark:text-slate-400 dark:hover:text-cyan-400 transition-colors">
                        {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
