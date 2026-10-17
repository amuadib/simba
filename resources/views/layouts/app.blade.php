<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        setInterval(() => {
            fetch('/csrf-refresh')
                .then(r => r.json())
                .then(d => {
                    document.querySelector('meta[name="csrf-token"]').content = d.token;
                });
        }, 5 * 60 * 1000);
    </script>
    <title>@yield('title', setting('nama_aplikasi') ?? env('APP_NAME')) - SIMBA</title>

    {{-- Google Fonts: Plus Jakarta Sans & Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    {{-- Local Assets & Bootstrap Icons --}}
    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">

    {{-- Tailwind CSS v4 via Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const theme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <style>
        /* ========================================================
           MODERN THEME VARIABLES INSPIRED BY LOGO PALETTE
           Deep Ocean Blue | Electric Cyan | Vibrant Emerald | Lime
        ======================================================== */
        :root {
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-display: 'Outfit', 'Plus Jakarta Sans', sans-serif;

            /* Light Mode */
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-subtle: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            
            /* Sidebar */
            --sidebar-bg: rgba(255, 255, 255, 0.95);
            --sidebar-border: #e2e8f0;
            --sidebar-text: #334155;
            --sidebar-muted: #64748b;
            --sidebar-active-bg: linear-gradient(135deg, rgba(2, 132, 199, 0.12) 0%, rgba(16, 185, 129, 0.08) 100%);
            --sidebar-active-text: #0284c7;

            /* Table */
            --table-bg: #ffffff;
            --table-border: #e2e8f0;
            --table-head-bg: #f8fafc;
            --table-row-hover: #f1f5f9;
            --table-text: #1e293b;
            --table-text-muted: #64748b;

            /* Form */
            --form-label-color: #1e293b;
            --form-label-weight: 600;
        }

        [data-theme="dark"] {
            /* Dark Mode: Cosmic Deep Gear Navy */
            --bg-main: #060e1a;
            --bg-card: #0a1b32;
            --bg-card-subtle: #0f2442;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;

            /* Sidebar */
            --sidebar-bg: #071528;
            --sidebar-border: rgba(56, 189, 248, 0.14);
            --sidebar-text: #cbd5e1;
            --sidebar-muted: #64748b;
            --sidebar-active-bg: linear-gradient(135deg, rgba(2, 132, 199, 0.25) 0%, rgba(16, 185, 129, 0.15) 100%);
            --sidebar-active-text: #38bdf8;

            /* Table */
            --table-bg: #09172c;
            --table-border: rgba(56, 189, 248, 0.12);
            --table-head-bg: #071224;
            --table-row-hover: #0c203b;
            --table-text: #f1f5f9;
            --table-text-muted: #94a3b8;
            --bs-table-color: #f8fafc;
            --bs-table-bg: transparent;
            --bs-table-striped-color: #f8fafc;
            --bs-table-hover-color: #ffffff;

            /* Form */
            --form-label-color: #f1f5f9;
        }

        body {
            font-family: var(--font-sans);
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            transition: background .3s, color .3s;
        }

        h1, h2, h3, h4, .font-heading {
            font-family: var(--font-display);
            letter-spacing: -0.02em;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #0284c7;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* CARD STYLING */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--table-border);
            border-radius: 1rem;
            transition: all 0.25s ease;
        }

        /* TABLES */
        .table {
            color: var(--table-text) !important;
            background-color: var(--table-bg);
            border-color: var(--table-border) !important;
            vertical-align: middle;
        }
        .table th, .table td {
            border-color: var(--table-border) !important;
            background-color: var(--table-bg);
            padding: 0.75rem 1rem;
        }
        .table thead th {
            background-color: var(--table-head-bg);
            color: var(--table-text);
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--table-border) !important;
        }
        .table tbody tr:hover td {
            background-color: var(--table-row-hover) !important;
        }

        /* FORM CONTROLS */
        .form-control, .form-select {
            background-color: var(--bg-card);
            border: 1.5px solid var(--table-border);
            color: var(--text-main);
            border-radius: 0.65rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--bg-card);
            border-color: #0284c7;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
            color: var(--text-main);
        }
        [data-theme="dark"] .form-control, [data-theme="dark"] .form-select {
            background-color: #0a1b32;
            border-color: rgba(56, 189, 248, 0.2);
            color: #f8fafc;
        }
        [data-theme="dark"] .form-control:focus, [data-theme="dark"] .form-select:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2);
            color: #ffffff;
        }

        /* MODALS */
        .modal-content {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--table-border);
            border-radius: 1.25rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            border-bottom: 1px solid var(--table-border);
            padding: 1.25rem 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid var(--table-border);
            padding: 1rem 1.5rem;
        }

        /* TOAST FLOATING ALERT */
        .toast-msg {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 99999;
            min-width: 280px;
            max-width: 420px;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #0284c7 0%, #00b4d8 50%, #10b981 100%);
            box-shadow: 0 15px 35px -5px rgba(2, 132, 199, 0.4), 0 0 20px rgba(16, 185, 129, 0.2);
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-msg.show {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .toast-msg.error {
            background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%);
            box-shadow: 0 15px 35px -5px rgba(225, 29, 72, 0.4);
        }
    </style>

    @livewireStyles
    @stack('styles')
</head>

<body x-data="{ 
    theme: localStorage.getItem('theme') || 'dark',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        document.documentElement.setAttribute('data-theme', this.theme);
    }
}" :data-theme="theme" class="bg-circuit-ambient min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200">

    <!-- TOP MODERN NAVBAR -->
    <header class="sticky top-0 z-40 glass-panel border-b border-sky-500/15 backdrop-blur-xl transition-all">
        <div class="px-4 py-2.5 flex items-center justify-between gap-4">
            
            {{-- Brand & Mobile Toggle --}}
            <div class="flex items-center gap-3">
                <button class="lg:hidden p-2 rounded-xl text-slate-500 hover:text-cyan-500 hover:bg-cyan-500/10 transition-colors" 
                        data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Buka Menu">
                    <i class="bi bi-list text-2xl"></i>
                </button>

                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-decoration-none group">
                    <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500/20 via-cyan-500/15 to-emerald-500/20 p-1.5 ring-1 ring-cyan-500/30 shadow-sm group-hover:scale-105 transition-transform">
                        <img src="{{ logo_url() }}" alt="SIMBA Logo" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-base md:text-lg tracking-tight font-display text-gradient-logo">
                                {{ setting('nama_aplikasi', 'SIMBA') }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                {{ session('tahun_ajaran_nama') ?? 'Aktif' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground m-0 leading-none hidden sm:block">Sistem Informasi Manajemen Lembaga</p>
                    </div>
                </a>
            </div>

            {{-- Right Actions: Live Date Clock, Theme Toggle, User Profile --}}
            <div class="flex items-center gap-2 md:gap-3">
                
                {{-- Live Clock Chip --}}
                <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-cyan-500/20 text-xs font-medium text-slate-600 dark:text-slate-300 shadow-sm">
                    <i class="bi bi-clock-history text-cyan-500 animate-pulse"></i>
                    <span id="tanggal_live">Memuat waktu...</span>
                </div>

                {{-- Dark / Light Theme Toggle Button --}}
                <button @click="toggleTheme()" 
                        class="p-2 w-9 h-9 rounded-xl flex items-center justify-center text-slate-600 dark:text-cyan-300 hover:bg-slate-100 dark:hover:bg-cyan-500/15 border border-slate-200 dark:border-cyan-500/25 transition-all shadow-sm" 
                        title="Ganti Tema">
                    <i class="bi bi-moon-stars-fill text-sm" x-show="theme === 'light'"></i>
                    <i class="bi bi-sun-fill text-amber-400 text-sm" x-show="theme === 'dark'"></i>
                </button>

                @auth
                    {{-- User Profile / Logout --}}
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-800">
                        <div class="hidden sm:block text-right">
                            <div class="text-xs font-bold leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-[10px] text-emerald-500 font-semibold uppercase tracking-wider">{{ auth()->user()->role ?? 'Admin' }}</div>
                        </div>

                        <form method="POST" action="/logout" class="inline m-0">
                            @csrf
                            <button type="submit" 
                                    class="p-2 rounded-xl text-rose-500 hover:bg-rose-500/10 border border-rose-500/20 hover:border-rose-500/40 transition-all flex items-center gap-1.5 text-xs font-semibold"
                                    title="Logout dari akun">
                                <i class="bi bi-box-arrow-right text-sm"></i>
                                <span class="hidden md:inline">Keluar</span>
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    {{-- BODY WRAPPER --}}
    <div class="flex flex-1 w-full overflow-hidden">
        
        {{-- SIDEBAR COMPONENT --}}
        @include('layouts.sidebar')

        {{-- MAIN CONTENT AREA --}}
        <main class="flex-1 p-3 sm:p-5 md:p-6 overflow-y-auto" style="min-width: 0;">
            @if ($errors->any())
                <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400">
                    <div class="font-bold flex items-center gap-2 mb-1">
                        <i class="bi bi-exclamation-octagon-fill"></i> Terjadi Kesalahan
                    </div>
                    <ul class="list-disc pl-5 m-0 text-sm">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset

            {{-- MODERN FOOTER --}}
            <footer class="mt-8 pt-4 pb-2 border-t border-slate-200/80 dark:border-slate-800/80 text-center text-xs text-slate-500 dark:text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>© {{ date('Y') }} <strong>{{ config('local.app.nama') ?? env('APP_NAME') }}</strong> — {{ config('local.lembaga.99.nama') ?? 'Miftahul Ulum' }}</span>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1 text-cyan-600 dark:text-cyan-400">
                        <i class="bi bi-shield-check"></i> SIMBA v2.0
                    </span>
                    <span>•</span>
                    <span class="text-slate-400">Bekerja dengan Baik & Cerdas</span>
                </div>
            </footer>
        </main>
    </div>

    {{-- FLOATING TOAST CONTAINER --}}
    <div id="toast" class="toast-msg">
        <i class="bi bi-check-circle-fill text-lg"></i>
        <span id="toast-text">Pemberitahuan</span>
    </div>

    {{-- SCRIPTS --}}
    <script src="{{ asset('bootstrap.bundle.min.js') }}"></script>
    <script>
        const now = new Date("{{ now() }}");
        const clockOptions = {
            weekday: 'long',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };

        function updateTanggalLive() {
            now.setSeconds(now.getSeconds() + 1);
            const el = document.getElementById('tanggal_live');
            if (el) {
                el.innerText = now.toLocaleDateString('id-ID', clockOptions).replace(' pukul ', ', ').replaceAll('.', ':');
            }
        }
        setInterval(updateTanggalLive, 1000);
        updateTanggalLive();
    </script>
    <script>
        let toastTimer = null;

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastText = document.getElementById('toast-text');
            if (!toast) return;

            if (toastText) {
                toastText.textContent = message;
            } else {
                toast.textContent = message;
            }

            const iconEl = toast.querySelector('i');
            if (iconEl) {
                iconEl.className = type === 'error' ? 'bi bi-x-circle-fill text-lg' : 'bi bi-check-circle-fill text-lg';
            }

            toast.className = 'toast-msg show' + (type === 'error' ? ' error' : '');

            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        // Global Livewire Listener
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('toast', (event) => {
                const data = Array.isArray(event) ? event[0] : event;
                showToast(data.message, data.type || 'success');
            });
        });

        // Handle Session Flash
        document.addEventListener('livewire:navigated', () => {
            @if (session('success'))
                showToast("{{ session('success') }}", 'success');
            @endif
            @if (session('error'))
                showToast("{{ session('error') }}", 'error');
            @endif
        });

        @if (session('success'))
            showToast("{{ session('success') }}", 'success');
        @endif
        @if (session('error'))
            showToast("{{ session('error') }}", 'error');
        @endif
    </script>

    @livewireScripts
    @stack('scripts')
</body>

</html>
