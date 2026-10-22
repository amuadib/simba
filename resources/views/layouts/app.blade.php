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
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
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

        [data-theme="dark"], [data-bs-theme="dark"], .dark {
            /* Dark Mode: Cosmic Deep Gear Navy */
            --bg-main: #060e1a;
            --bg-card: #0a1b32;
            --bg-card-subtle: #0f2442;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --bs-body-color: #f8fafc !important;
            --bs-body-color-rgb: 248, 250, 252 !important;
            --bs-body-bg: #060e1a !important;

            /* Sidebar */
            --sidebar-bg: #071528;
            --sidebar-border: rgba(56, 189, 248, 0.14);
            --sidebar-text: #cbd5e1;
            --sidebar-muted: #94a3b8;
            --sidebar-active-bg: linear-gradient(135deg, rgba(2, 132, 199, 0.25) 0%, rgba(16, 185, 129, 0.15) 100%);
            --sidebar-active-text: #38bdf8;

            /* Table */
            --table-bg: #09172c;
            --table-border: rgba(56, 189, 248, 0.15);
            --table-head-bg: #071224;
            --table-row-hover: #0e223f;
            --table-text: #f8fafc;
            --table-text-muted: #94a3b8;
            --bs-table-color: #f8fafc !important;
            --bs-table-striped-color: #f8fafc !important;
            --bs-table-hover-color: #ffffff !important;

            /* Form */
            --form-label-color: #f1f5f9;
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: var(--font-sans);
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            transition: background .3s, color .3s;
        }

        h1, h2, h3, h4, h5, h6, .font-heading {
            font-family: var(--font-display);
            letter-spacing: -0.02em;
            color: var(--text-main);
        }

        /* TEXT MUTED & SECONDARY IN DARK MODE */
        [data-theme="dark"] .text-muted,
        [data-bs-theme="dark"] .text-muted,
        .dark .text-muted {
            color: #94a3b8 !important;
        }

        [data-theme="dark"] .text-secondary,
        [data-bs-theme="dark"] .text-secondary,
        .dark .text-secondary {
            color: #cbd5e1 !important;
        }

        /* NICKNAME HIGHLIGHTING (PANGGILAN SISWA: L = BIRU, P = PINK) */
        .nickname {
            font-weight: 600;
        }
        .nickname-L,
        .nickname-l {
            color: #007bff !important;
        }
        .nickname-P,
        .nickname-p {
            color: rgba(243, 6, 223, 0.856) !important;
        }
        [data-theme="dark"] .nickname-L,
        [data-bs-theme="dark"] .nickname-L,
        .dark .nickname-L,
        [data-theme="dark"] .nickname-l,
        [data-bs-theme="dark"] .nickname-l,
        .dark .nickname-l {
            color: #0284c7 !important;
        }
        [data-theme="dark"] .nickname-P,
        [data-bs-theme="dark"] .nickname-P,
        .dark .nickname-P,
        [data-theme="dark"] .nickname-p,
        [data-bs-theme="dark"] .nickname-p,
        .dark .nickname-p {
            color: rgba(243, 6, 223, 0.9) !important;
        }

        /* CARD STYLING */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--table-border);
            border-radius: 1rem;
            transition: all 0.25s ease;
        }
        [data-theme="dark"] .card,
        [data-bs-theme="dark"] .card,
        .dark .card {
            background: #0a1b32;
            border-color: rgba(56, 189, 248, 0.16);
            color: #f8fafc;
        }

        /* CARD HEADER & FOOTER */
        .card-header {
            background-color: rgba(0, 0, 0, 0.02);
            border-bottom: 1px solid var(--table-border);
        }
        .card-footer {
            background-color: rgba(0, 0, 0, 0.02);
            border-top: 1px solid var(--table-border);
        }
        [data-theme="dark"] .card-header,
        [data-bs-theme="dark"] .card-header,
        .dark .card-header {
            background-color: #0e2444 !important;
            color: #f8fafc !important;
            border-bottom: 1px solid rgba(56, 189, 248, 0.16) !important;
        }
        [data-theme="dark"] .card-header.bg-white,
        [data-bs-theme="dark"] .card-header.bg-white,
        .dark .card-header.bg-white,
        [data-theme="dark"] .card-header.bg-light,
        [data-bs-theme="dark"] .card-header.bg-light,
        .dark .card-header.bg-light {
            background-color: #0e2444 !important;
            color: #f8fafc !important;
            border-bottom: 1px solid rgba(56, 189, 248, 0.16) !important;
        }
        [data-theme="dark"] .card-footer,
        [data-bs-theme="dark"] .card-footer,
        .dark .card-footer {
            background-color: #0e2444 !important;
            color: #f8fafc !important;
            border-top: 1px solid rgba(56, 189, 248, 0.16) !important;
        }

        /* OVERRIDE IN-CARD BG-LIGHT & BG-WHITE FOR DARK THEME */
        [data-theme="dark"] .card .bg-light,
        [data-bs-theme="dark"] .card .bg-light,
        .dark .card .bg-light {
            background-color: rgba(15, 23, 42, 0.6) !important;
            color: #e2e8f0 !important;
            border-color: rgba(56, 189, 248, 0.15) !important;
        }
        [data-theme="dark"] .card .bg-white,
        [data-bs-theme="dark"] .card .bg-white,
        .dark .card .bg-white {
            background-color: #0d2242 !important;
            color: #f8fafc !important;
        }

        /* TABLE-LIGHT IN DARK THEME */
        [data-theme="dark"] .table-light,
        [data-bs-theme="dark"] .table-light,
        .dark .table-light,
        [data-theme="dark"] thead.table-light th,
        [data-bs-theme="dark"] thead.table-light th,
        .dark thead.table-light th {
            background-color: #071224 !important;
            color: #38bdf8 !important;
            border-color: rgba(56, 189, 248, 0.2) !important;
        }

        /* NAV-PILLS DARK THEME */
        [data-theme="dark"] .nav-pills .nav-link,
        [data-bs-theme="dark"] .nav-pills .nav-link,
        .dark .nav-pills .nav-link {
            color: #94a3b8 !important;
            background-color: rgba(15, 23, 42, 0.45) !important;
            border: 1px solid rgba(56, 189, 248, 0.15) !important;
            font-weight: 600;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .nav-pills .nav-link:hover,
        [data-bs-theme="dark"] .nav-pills .nav-link:hover,
        .dark .nav-pills .nav-link:hover {
            color: #38bdf8 !important;
            background-color: rgba(2, 132, 199, 0.15) !important;
            border-color: rgba(56, 189, 248, 0.35) !important;
        }
        [data-theme="dark"] .nav-pills .nav-link.active,
        [data-bs-theme="dark"] .nav-pills .nav-link.active,
        .dark .nav-pills .nav-link.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, #0284c7 0%, #00b4d8 100%) !important;
            border-color: transparent !important;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35) !important;
        }

        /* BOOTSTRAP TABS (.nav-tabs) IN DARK MODE */
        [data-theme="dark"] .nav-tabs,
        [data-bs-theme="dark"] .nav-tabs,
        .dark .nav-tabs {
            border-bottom: 1px solid rgba(56, 189, 248, 0.2) !important;
        }
        [data-theme="dark"] .nav-tabs .nav-link,
        [data-bs-theme="dark"] .nav-tabs .nav-link,
        .dark .nav-tabs .nav-link {
            color: #94a3b8 !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: 0.75rem 0.75rem 0 0;
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .nav-tabs .nav-link:hover,
        [data-bs-theme="dark"] .nav-tabs .nav-link:hover,
        .dark .nav-tabs .nav-link:hover {
            color: #38bdf8 !important;
            background: rgba(2, 132, 199, 0.12) !important;
            border-color: rgba(56, 189, 248, 0.25) rgba(56, 189, 248, 0.25) transparent !important;
        }
        [data-theme="dark"] .nav-tabs .nav-link.active,
        [data-bs-theme="dark"] .nav-tabs .nav-link.active,
        .dark .nav-tabs .nav-link.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, #0284c7 0%, #00b4d8 100%) !important;
            border-color: transparent !important;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35) !important;
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
            color: var(--table-text) !important;
        }
        .table thead th {
            background-color: var(--table-head-bg);
            color: var(--table-text) !important;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--table-border) !important;
        }
        .table tbody tr:hover td {
            background-color: var(--table-row-hover) !important;
        }

        /* PRESERVE EXPLICIT TEXT CONTRAST IN TABLES */
        .table th.text-white,
        .table td.text-white,
        .table thead th.text-white,
        .table th.text-light,
        .table td.text-light,
        .table thead th.text-light,
        .table th [class*="text-white"],
        .table td [class*="text-white"],
        .table .badge.bg-dark,
        .table .badge.bg-secondary,
        .table .badge.bg-primary,
        .table .badge.bg-success,
        .table .badge.bg-danger,
        [data-theme="dark"] .table th.text-white,
        [data-bs-theme="dark"] .table th.text-white,
        .dark .table th.text-white,
        [data-theme="dark"] .table thead th.text-white,
        [data-bs-theme="dark"] .table thead th.text-white,
        .dark .table thead th.text-white {
            color: #ffffff !important;
        }

        .table th.text-dark,
        .table td.text-dark,
        .table thead th.text-dark,
        .table th [class*="text-dark"],
        .table td [class*="text-dark"],
        [data-theme="dark"] .table th.text-dark,
        [data-bs-theme="dark"] .table th.text-dark,
        .dark .table th.text-dark,
        [data-theme="dark"] .table thead th.text-dark,
        [data-bs-theme="dark"] .table thead th.text-dark,
        .dark .table thead th.text-dark {
            color: #0f172a !important;
        }

        /* FORCED TABLE DARK MODE CELLS SPECIFICITY */
        [data-theme="dark"] .table,
        [data-bs-theme="dark"] .table,
        .dark .table,
        [data-theme="dark"] .table > :not(caption) > * > *,
        [data-bs-theme="dark"] .table > :not(caption) > * > *,
        .dark .table > :not(caption) > * > * {
            color: #f1f5f9 !important;
            background-color: #09172c !important;
            border-color: rgba(56, 189, 248, 0.12) !important;
            --bs-table-color: #f1f5f9 !important;
            --bs-table-bg: #09172c !important;
        }
        [data-theme="dark"] .table thead th,
        [data-bs-theme="dark"] .table thead th,
        .dark .table thead th {
            background-color: #071224 !important;
            color: #38bdf8 !important;
            border-bottom: 2px solid rgba(56, 189, 248, 0.25) !important;
        }
        [data-theme="dark"] .table tbody tr:hover > *,
        [data-bs-theme="dark"] .table tbody tr:hover > *,
        .dark .table tbody tr:hover > * {
            background-color: #0e223f !important;
            color: #ffffff !important;
        }

        /* FORM CONTROLS IN DARK MODE */
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
        [data-theme="dark"] .form-control, 
        [data-theme="dark"] .form-select,
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select,
        .dark .form-control,
        .dark .form-select {
            background-color: #0b1f3b !important;
            border-color: rgba(56, 189, 248, 0.25) !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .form-control:focus, 
        [data-theme="dark"] .form-select:focus,
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus,
        .dark .form-control:focus,
        .dark .form-select:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2) !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control::placeholder,
        [data-bs-theme="dark"] .form-control::placeholder,
        .dark .form-control::placeholder {
            color: #94a3b8 !important;
            opacity: 1;
        }
        [data-theme="dark"] .form-select option,
        [data-bs-theme="dark"] .form-select option,
        .dark .form-select option {
            background-color: #0a1b32 !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .input-group-text,
        [data-bs-theme="dark"] .input-group-text,
        .dark .input-group-text {
            background-color: #071528 !important;
            border-color: rgba(56, 189, 248, 0.25) !important;
            color: #38bdf8 !important;
        }
        [data-theme="dark"] .input-group-text i,
        [data-bs-theme="dark"] .input-group-text i,
        .dark .input-group-text i {
            color: #38bdf8 !important;
        }

        /* BADGE OVERRIDES FOR ACCURATE TEXT COLORS IN LIGHT & DARK MODE */
        .badge[class*="text-"] {
            --bs-badge-color: currentColor !important;
            color: inherit !important;
        }
        .badge.bg-light {
            color: #1e293b !important;
            --bs-badge-color: #1e293b !important;
        }
        .badge.bg-light.text-muted {
            color: #64748b !important;
            --bs-badge-color: #64748b !important;
        }

        /* BADGES IN DARK MODE */
        [data-theme="dark"] .badge.bg-light,
        [data-bs-theme="dark"] .badge.bg-light,
        .dark .badge.bg-light {
            background-color: rgba(56, 189, 248, 0.15) !important;
            color: #7dd3fc !important;
            border: 1px solid rgba(56, 189, 248, 0.3) !important;
        }
        [data-theme="dark"] .badge.bg-secondary,
        [data-bs-theme="dark"] .badge.bg-secondary,
        .dark .badge.bg-secondary {
            background-color: rgba(148, 163, 184, 0.22) !important;
            color: #e2e8f0 !important;
            border: 1px solid rgba(148, 163, 184, 0.3) !important;
        }

        /* OUTLINE BUTTONS IN DARK MODE */
        [data-theme="dark"] .btn-outline-primary,
        [data-bs-theme="dark"] .btn-outline-primary,
        .dark .btn-outline-primary {
            color: #38bdf8 !important;
            border-color: rgba(56, 189, 248, 0.4) !important;
        }
        [data-theme="dark"] .btn-outline-primary:hover,
        [data-bs-theme="dark"] .btn-outline-primary:hover,
        .dark .btn-outline-primary:hover {
            background-color: #0284c7 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-info,
        [data-bs-theme="dark"] .btn-outline-info,
        .dark .btn-outline-info {
            color: #38bdf8 !important;
            border-color: rgba(56, 189, 248, 0.4) !important;
        }
        [data-theme="dark"] .btn-outline-info:hover,
        [data-bs-theme="dark"] .btn-outline-info:hover,
        .dark .btn-outline-info:hover {
            background-color: #0284c7 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-warning,
        [data-bs-theme="dark"] .btn-outline-warning,
        .dark .btn-outline-warning {
            color: #fbbf24 !important;
            border-color: rgba(251, 191, 36, 0.4) !important;
        }
        [data-theme="dark"] .btn-outline-warning:hover,
        [data-bs-theme="dark"] .btn-outline-warning:hover,
        .dark .btn-outline-warning:hover {
            background-color: #d97706 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-danger,
        [data-bs-theme="dark"] .btn-outline-danger,
        .dark .btn-outline-danger {
            color: #f87171 !important;
            border-color: rgba(248, 113, 113, 0.4) !important;
        }
        [data-theme="dark"] .btn-outline-danger:hover,
        [data-bs-theme="dark"] .btn-outline-danger:hover,
        .dark .btn-outline-danger:hover {
            background-color: #dc2626 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-success,
        [data-bs-theme="dark"] .btn-outline-success,
        .dark .btn-outline-success {
            color: #34d399 !important;
            border-color: rgba(52, 211, 153, 0.4) !important;
        }
        [data-theme="dark"] .btn-outline-success:hover,
        [data-bs-theme="dark"] .btn-outline-success:hover,
        .dark .btn-outline-success:hover {
            background-color: #059669 !important;
            color: #ffffff !important;
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
        .toast-msg.warning {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            box-shadow: 0 15px 35px -5px rgba(217, 119, 6, 0.4);
        }

        /* TOAST RESPONSIVE MOBILE (TENGAH ATAS) */
        @media (max-width: 768px) {
            .toast-msg {
                top: max(16px, env(safe-area-inset-top, 16px));
                left: 16px;
                right: 16px;
                min-width: 0;
                max-width: calc(100vw - 32px);
                width: fit-content;
                margin-left: auto;
                margin-right: auto;
                padding: 12px 16px;
                font-size: 13.5px;
                box-shadow: 0 14px 35px -5px rgba(0, 0, 0, 0.4), 0 0 20px rgba(2, 132, 199, 0.25);
            }
        }

        /* SIDEBAR DESKTOP STICKY DOCKING UNDER HEADER */
        @media (min-width: 992px) {
            #sidebar {
                position: sticky !important;
                top: 4rem !important;
                height: calc(100vh - 4rem) !important;
                height: calc(100dvh - 4rem) !important;
                z-index: 30 !important;
            }
        }
    </style>

    @livewireStyles
    @stack('styles')
</head>

<body x-data="{ 
    theme: localStorage.getItem('theme') || 'light',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        document.documentElement.setAttribute('data-theme', this.theme);
        document.documentElement.setAttribute('data-bs-theme', this.theme);
        if (this.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}" :data-theme="theme" :data-bs-theme="theme" class="bg-circuit-ambient min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200">

    <!-- TOP MODERN NAVBAR -->
    <header class="sticky top-0 z-50 h-16 glass-panel border-b border-sky-500/15 backdrop-blur-xl transition-all flex items-center">
        <div class="px-4 w-full flex items-center justify-between gap-4">
            
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
    <div class="flex flex-1 w-full">
        
        {{-- SIDEBAR COMPONENT --}}
        @include('layouts.sidebar')

        {{-- MAIN CONTENT AREA --}}
        <main class="flex-1 p-3 sm:p-5 md:p-6" style="min-width: 0;">
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
                if (type === 'error') {
                    iconEl.className = 'bi bi-x-circle-fill text-lg';
                } else if (type === 'warning') {
                    iconEl.className = 'bi bi-exclamation-triangle-fill text-lg';
                } else {
                    iconEl.className = 'bi bi-check-circle-fill text-lg';
                }
            }

            toast.className = 'toast-msg show' + (type === 'error' ? ' error' : (type === 'warning' ? ' warning' : ''));

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
