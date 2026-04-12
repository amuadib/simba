@php
    $segments = request()->segments();
@endphp<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-3">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Home</a>
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

            @if ($loop->last)
                <li class="breadcrumb-item active" aria-current="page">
                    {{ $label }}
                </li>
            @else
                <li class="breadcrumb-item">
                    <a href="{{ $url }}">{{ $label }}</a>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
