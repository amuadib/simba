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
    <title>@yield('title', 'Presensi Sekolah')</title>
    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">
    @stack('styles')
    <style>
        .breadcrumb a {
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* =====================
   THEME VARIABLES
===================== */
        :root {
            --bg-main: #f4f6f9;
            --bg-card: #ffffff;
            --bg-sidebar: #0d6efd;
            --text-main: #212529;
        }

        [data-theme="dark"] {
            --bg-main: #0f172a;
            --bg-card: #020617;
            --bg-sidebar: #020617;
            --text-main: #e5e7eb;
        }

        /* =====================
   GLOBAL
===================== */
        body {
            background: var(--bg-main);
            color: var(--text-main);
            transition: background .3s, color .3s;
        }

        .card {
            background: var(--bg-card);
            border: none;
        }

        .text-muted {
            color: #94a3b8 !important;
        }

        /* =====================
   SIDEBAR
===================== */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--bg-sidebar);
        }

        .sidebar a {
            color: #cbd5f5;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            margin: 4px 10px;
            border-radius: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, .08);
            color: #38bdf8;
        }

        /* =====================
   NAVBAR
===================== */
        .navbar {
            background: var(--bg-card);
        }

        /* =====================
   STAT CARD
===================== */
        .stat-card h4 {
            font-weight: bold;
        }

        /* =====================
   LIGHT MODE SIDEBAR FIX
===================== */
        :root {
            --sidebar-bg: #f1f5f9;
            --sidebar-border: #e2e8f0;
            --sidebar-text: #334155;
            --sidebar-muted: #64748b;
            --sidebar-active-bg: #e0f2fe;
            --sidebar-active-text: #0284c7;
        }

        [data-theme="light"] .sidebar {
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
        }

        [data-theme="light"] .sidebar div {
            color: var(--sidebar-text);
        }

        [data-theme="dark"] .sidebar div {
            color: white;
        }

        [data-theme="light"] .sidebar a {
            color: var(--sidebar-text);
            font-weight: 500;
        }

        [data-theme="light"] .sidebar a i {
            color: var(--sidebar-muted);
        }

        [data-theme="light"] .sidebar a:hover {
            background: #e2e8f0;
        }

        [data-theme="light"] .sidebar a.active {
            border-left: 4px solid #0284c7;
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 600;
        }

        [data-theme="light"] .sidebar a.active i {
            color: var(--sidebar-active-text);
        }

        .offcanvas.sidebar {
            box-shadow: 0 10px 30px rgba(0, 0, 0, .15);
        }
    </style>
</head>

<body data-theme="dark">

    <!-- NAVBAR -->
    <nav class="navbar px-3 shadow-sm">
        <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="bi bi-list"></i>
        </button>

        <span class="fw-bold ms-2">📘 Sistem Presensi</span>
        <div class="d-flex align-items-center ms-auto">
            <div class="text-muted mx-2" id="tanggal">
                loading ...
            </div>
            <button id="themeToggle" class="btn btn-sm btn-outline-info me-2">
                <i class="bi bi-moon-stars"></i>
            </button>
            @auth
                <form method="POST" action="/logout" class="float-end">
                    @csrf
                    <button class="no-print btn btn-sm btn-danger">Logout {{ auth()->user()->name }}</button>
                </form>
            @endauth
        </div>
    </nav>

    <div class="d-flex">

        <!-- SIDEBAR -->
        @include('layouts.sidebar')

        <!-- CONTENT -->
        <div class="flex-fill p-4">

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                @foreach ($errors->all() as $e)
                    <div class="alert alert-danger">{{ $e }}</div>
                @endforeach
            @endif

            @yield('content')
            <footer class="text-muted mt-4 text-center">
                © 2026 Sistem Presensi
            </footer>
        </div>
    </div>

    <!-- SCRIPT -->
    <script>
        const toggle = document.getElementById('themeToggle');
        const body = document.body;

        toggle.onclick = () => {
            const theme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        };

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) body.setAttribute('data-theme', savedTheme);
    </script>

    <script src="{{ asset('bootstrap.bundle.min.js') }}"></script>
    <script>
        var serverTime = "{{ now() }}";
        var now = new Date(serverTime);
        var options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };

        function updateTanggal() {
            now.setSeconds(now.getSeconds() + 1);
            document.getElementById('tanggal').innerHTML = now.toLocaleDateString('id-ID', options).replace(' pukul ',
                ', ').replaceAll('.',
                ':');
        }
        setInterval(updateTanggal, 1000);
    </script>
    @stack('scripts')
</body>

</html>
