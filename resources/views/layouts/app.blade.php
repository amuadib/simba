<!doctype html>
<html lang="id" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Presensi Sekolah')</title>
    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    @stack('styles')
</head>

<body class="container py-4">

    <div class="mb-1">
        <h4>📘 Sistem Presensi</h4>
        @auth
            <form method="POST" action="/logout" class="float-end">
                @csrf
                <button class="no-print btn btn-sm btn-outline-danger">Logout {{ auth()->user()->name }}</button>
            </form>
        @endauth
    </div>
    <nav class="mb-3">
        <a class="btn btn-sm btn-primary" href="{{ route('pembelajaran.presensi.index') }}">Rekap Presensi</a>
        <a class="btn btn-sm btn-success" href="{{ route('pembelajaran.index') }}">Pembelajaran</a>
        <a class="btn btn-sm btn-outline-success" href="{{ route('siswa.index') }}">Siswa</a>
        <a class="btn btn-sm btn-outline-success" href="{{ route('rombel.index') }}">Rombel</a>
        <a class="btn btn-sm btn-outline-success" href="{{ route('pelajaran.index') }}">Pelajaran</a>
        <a class="btn btn-sm btn-outline-success" href="{{ route('tahun_ajaran.index') }}">Tahun Ajaran</a>
        <a class="btn btn-sm btn-danger" href="{{ route('backup.index') }}">Backup</a>
        <button id="themeToggle" class="btn btn-sm btn-outline-secondary float-end">
            🌙 Dark Mode
        </button>
    </nav>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        @foreach ($errors->all() as $e)
            <div class="alert alert-danger">{{ $e }}</div>
        @endforeach
    @endif

    @yield('content')
    </div>
    <script src="{{ asset('bootstrap.bundle.min.js') }}"></script>
    <script>
        const html = document.documentElement;
        const btn = document.getElementById('themeToggle');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-bs-theme', savedTheme);
        btn.innerText = savedTheme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';

        btn.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            btn.innerText = next === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
        });
    </script>
    @stack('scripts')
</body>

</html>
