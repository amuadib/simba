<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Masuk ke Akun' }} - {{ setting('nama_aplikasi') ?? env('APP_NAME') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    @livewireStyles
</head>

<body x-data="{ 
    theme: localStorage.getItem('theme') || 'dark',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        document.documentElement.setAttribute('data-theme', this.theme);
    }
}" :data-theme="theme" class="bg-circuit-ambient min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans antialiased">

    {{-- Ambient Decorative Glow Elements matching Logo --}}
    <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-gradient-to-tr from-sky-600/20 to-cyan-400/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-gradient-to-br from-emerald-500/20 to-lime-400/20 blur-3xl pointer-events-none"></div>

    {{-- Top Utility Bar: Back to Home & Theme Toggle --}}
    <div class="absolute top-5 left-5 right-5 flex items-center justify-between z-20 max-w-5xl mx-auto">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200 dark:border-cyan-500/20 text-xs font-semibold text-slate-700 dark:text-cyan-300 hover:text-cyan-600 transition-colors text-decoration-none shadow-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Beranda
        </a>

        <button @click="toggleTheme()" class="p-2 w-9 h-9 rounded-xl flex items-center justify-center bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200 dark:border-cyan-500/20 text-slate-700 dark:text-cyan-300 shadow-sm transition-all" title="Ganti Tema">
            <i class="bi bi-moon-stars-fill text-xs" x-show="theme === 'light'"></i>
            <i class="bi bi-sun-fill text-amber-400 text-xs" x-show="theme === 'dark'"></i>
        </button>
    </div>

    {{-- Slot Container --}}
    <div class="w-full max-w-md relative z-10 my-12">
        {{ $slot }}
    </div>

    @livewireScripts
</body>

</html>
