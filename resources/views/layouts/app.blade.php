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
            /*sidebar*/
            --sidebar-bg: #f1f5f9;
            --sidebar-border: #e2e8f0;
            --sidebar-text: #334155;
            --sidebar-muted: #64748b;
            --sidebar-active-bg: #e0f2fe;
            --sidebar-active-text: #0284c7;
            /*table*/
            --table-bg: #ffffff;
            --table-border: #dee2e6;
            --table-head-bg: #f8f9fa;
            --table-row-hover: #f1f3f5;
            --table-text: #212529;
            --table-text-muted: #6c757d;
        }

        [data-theme="dark"] {
            --bg-main: #0f172a;
            --bg-card: #020617;
            --bg-sidebar: #020617;
            --text-main: #e5e7eb;
            --table-bg: #020617;
            --table-border: #1e293b;
            --table-head-bg: #020617;
            --table-row-hover: #020617;
            --table-text: #f1f5f9;
            --table-text-muted: #94a3b8;
            /* override */
            --bs-table-color: #f8fafc;
            --bs-table-bg: transparent;
            --bs-table-striped-color: #f8fafc;
            --bs-table-hover-color: #ffffff;
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
        :root {}

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

        /* TABEL */
        .table {
            color: var(--table-text) !important;
            background-color: var(--table-bg);
        }

        .table th,
        .table td {
            border-color: var(--table-border) !important;
            background-color: var(--table-bg);
        }

        .table thead th {
            background-color: var(--table-head-bg);
            color: var(--table-text) !important;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        [data-theme="dark"] .table thead th {
            color: #ffffff !important;
            font-weight: 600;
            letter-spacing: .04em;
        }

        [data-theme="dark"] .table td,
        [data-theme="dark"] .table th {
            color: #f8fafc;
            opacity: 1 !important;
        }

        [data-theme="dark"] .table td:first-child {
            color: #ffffff !important;
            font-weight: 500;
        }

        .table-bordered {
            border-color: var(--table-border);
        }

        .table tbody tr:hover td {
            background-color: var(--table-row-hover);
        }

        .table tbody tr:nth-child(even) td {
            background-color: color-mix(in srgb, var(--table-bg) 90%, #000 10%);
        }

        [data-theme="dark"] .table tbody tr:nth-child(even) td {
            background-color: #020617;
        }

        .table-sm th,
        .table-sm td {
            padding: .4rem .5rem;
            font-size: .85rem;
        }

        .table td.ellipsis,
        .table th.ellipsis {
            max-width: 100px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        /* TOAST */
        .toast-msg {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 220px;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #fff;
            background: #198754;
            /* default success */
            box-shadow: 0 4px 10px rgba(0, 0, 0, .25);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
        }

        /* show */
        .toast-msg.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* error */
        .toast-msg.error {
            background: #dc3545;
        }

        /* mobile */
        @media (max-width: 576px) {
            .toast-msg {
                left: 16px;
                right: 16px;
                bottom: 16px;
                text-align: center;
            }
        }
    </style>

    @stack('styles')
</head>

<body data-theme="dark">

    <!-- NAVBAR -->
    <nav class="navbar px-3 shadow-sm">
        <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="bi bi-list"></i>
        </button>

        <span class="fw-bold ms-2">📘 Sistem Presensi</span>
        <div class="d-flex align-items-center ms-auto">
            <div class="text-muted mx-2" id="tanggal_live">
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

    <div id="toast" class="toast-msg"></div>
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
        const now = new Date("{{ now() }}");
        const options = {
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
            document.getElementById('tanggal_live').innerHTML = now.toLocaleDateString('id-ID', options).replace(' pukul ',
                ', ').replaceAll('.',
                ':');
        }
        setInterval(updateTanggal, 1000);
    </script>
    <script>
        let toastTimer = null;

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            if (!toast) return;

            toast.textContent = message;
            toast.className = 'toast-msg show' + (type === 'error' ? ' error' : '');

            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
        }
    </script>

    @stack('scripts')
</body>

</html>
