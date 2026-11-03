<!doctype html>
<html lang="id" class="dark">

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Selamat Datang' }} - {{ setting('nama_aplikasi') ?? config('local.app.nama') ?? 'SIMBA' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal Resmi {{ config('local.lembaga.99.nama') ?? setting('nama_lembaga') ?? 'Yayasan' }} dan Satuan Pendidikan Naungan.">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Bootstrap Icons (dipertahankan) --}}
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">

    {{-- Tailwind CSS v4 via Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme immediately to prevent flashing
        (function() {
            const t = localStorage.getItem('theme') || 'dark';
            if (t === 'dark') document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        })();
    </script>

    <style>
        :root {
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-display: 'Outfit', 'Plus Jakarta Sans', sans-serif;

            /* LIGHT THEME (Logo Palette: Ocean Blue, Circuit Cyan, Emerald & Lime) */
            --bg-page: #f8fafc;
            --bg-page-subtle: #f0fdf4;
            --card-bg: rgba(255, 255, 255, 0.94);
            --card-bg-solid: #ffffff;
            --card-border: rgba(226, 232, 240, 0.9);
            --card-hover-border: rgba(2, 132, 199, 0.45);
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --primary: #0284c7;
            --primary-hover: #0369a1;
            --primary-light: rgba(2, 132, 199, 0.08);
            --accent-cyan: #00b4d8;
            --accent-emerald: #10b981;
            --accent-emerald-light: rgba(16, 185, 129, 0.08);
            --accent-lime: #84cc16;
            --navbar-bg: rgba(255, 255, 255, 0.88);
            --navbar-border: rgba(226, 232, 240, 0.8);
            --footer-bg: #071528;
            --footer-text: #94a3b8;
            --badge-bg: #e0f2fe;
            --badge-text: #0369a1;
            --card-shadow: 0 10px 30px -5px rgba(2, 132, 199, 0.06), 0 4px 6px -4px rgba(16, 185, 129, 0.03);
            --card-shadow-hover: 0 20px 35px -5px rgba(2, 132, 199, 0.16), 0 10px 10px -5px rgba(16, 185, 129, 0.06);
            --hero-gradient: radial-gradient(at 0% 0%, rgba(2, 132, 199, 0.14) 0px, transparent 60%),
                               radial-gradient(at 100% 10%, rgba(16, 185, 129, 0.10) 0px, transparent 50%),
                               radial-gradient(at 50% 100%, rgba(0, 180, 216, 0.08) 0px, transparent 60%);
        }

        .dark {
            /* DARK THEME (Cosmic Deep Gear Navy) */
            --bg-page: #060e1a;
            --bg-page-subtle: #09172c;
            --card-bg: rgba(10, 27, 50, 0.82);
            --card-bg-solid: #0a1b32;
            --card-border: rgba(56, 189, 248, 0.14);
            --card-hover-border: rgba(56, 189, 248, 0.5);
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --primary-light: rgba(56, 189, 248, 0.16);
            --accent-cyan: #00b4d8;
            --accent-emerald: #10b981;
            --accent-emerald-light: rgba(16, 185, 129, 0.16);
            --accent-lime: #84cc16;
            --navbar-bg: rgba(6, 14, 26, 0.88);
            --navbar-border: rgba(56, 189, 248, 0.12);
            --footer-bg: #040a14;
            --footer-text: #64748b;
            --badge-bg: rgba(2, 132, 199, 0.22);
            --badge-text: #7dd3fc;
            --card-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5);
            --card-shadow-hover: 0 25px 40px -10px rgba(2, 132, 199, 0.3), 0 0 15px rgba(0, 180, 216, 0.2);
            --hero-gradient: radial-gradient(at 0% 0%, rgba(2, 132, 199, 0.24) 0px, transparent 55%),
                               radial-gradient(at 100% 20%, rgba(16, 185, 129, 0.16) 0px, transparent 50%),
                               radial-gradient(at 50% 100%, rgba(0, 180, 216, 0.14) 0px, transparent 60%);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-page);
            background-image: var(--hero-gradient);
            background-attachment: fixed;
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-display);
            color: var(--text-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Glassmorphism Navbar */
        .welcome-navbar {
            background: var(--navbar-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--navbar-border);
            position: sticky;
            top: 0;
            z-index: 1040;
            transition: all 0.3s ease;
        }

        .navbar-brand-title {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-heading) !important;
        }

        .navbar-brand-subtitle {
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: -2px;
        }

        .nav-link-custom {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.925rem;
            padding: 0.5rem 0.85rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .nav-link-custom:hover {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        /* Theme Toggle Button */
        .btn-theme-toggle {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-heading);
            border-radius: 9999px;
            padding: 0.45rem 0.9rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-theme-toggle:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #0284c7 0%, #00b4d8 50%, #10b981 100%);
            border: none;
            color: #ffffff !important;
            font-weight: 600;
            border-radius: 12px;
            padding: 0.65rem 1.4rem;
            box-shadow: 0 8px 20px -4px rgba(2, 132, 199, 0.45);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #0369a1 0%, #0284c7 50%, #059669 100%);
            box-shadow: 0 12px 25px -4px rgba(0, 180, 216, 0.6);
            color: #ffffff !important;
        }

        .btn-outline-custom {
            background: var(--card-bg);
            border: 1.5px solid var(--card-border);
            color: var(--text-heading) !important;
            font-weight: 600;
            border-radius: 12px;
            padding: 0.65rem 1.4rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-custom:hover {
            border-color: var(--primary);
            color: var(--primary) !important;
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        /* Card Action Buttons (Chat WA & Detail) */
        .btn-action-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.42rem 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 9999px;
            text-decoration: none;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            line-height: 1.4;
            border: 1.5px solid transparent;
            cursor: pointer;
        }

        .btn-action-card:hover {
            transform: translateY(-2px);
        }

        .btn-action-card:active {
            transform: translateY(0);
        }

        .btn-action-card-wa {
            background-color: transparent;
            border-color: var(--accent-emerald);
            color: var(--accent-emerald) !important;
        }

        .btn-action-card-wa:hover {
            background-color: var(--accent-emerald);
            border-color: var(--accent-emerald);
            color: #ffffff !important;
            box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.35);
        }

        .btn-action-card-detail {
            background-color: transparent;
            border-color: var(--primary);
            color: var(--primary) !important;
        }

        .btn-action-card-detail:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #ffffff !important;
            box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.35);
        }

        /* Perfect Circle Icon Helpers */
        .icon-circle {
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 1;
        }

        .icon-circle i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .icon-circle svg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle-sm {
            width: 40px;
            height: 40px;
        }

        .icon-circle-md {
            width: 48px;
            height: 48px;
        }

        .icon-circle-lg {
            width: 54px;
            height: 54px;
        }

        /* Badges */
        .badge-subtle-primary {
            background-color: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-weight: 600;
            font-size: 0.775rem;
            letter-spacing: 0.03em;
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .badge-subtle-emerald {
            background-color: var(--accent-emerald-light);
            color: var(--accent-emerald);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-weight: 600;
            font-size: 0.775rem;
            padding: 0.35rem 0.8rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Card styles */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
            overflow: hidden;
        }

        .glass-card:hover {
            border-color: var(--card-hover-border);
            box-shadow: var(--card-shadow-hover);
        }

        /* Featured Lembaga 99 Hero Card */
        .featured-hero-card {
            position: relative;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid rgba(99, 102, 241, 0.3);
            border-radius: 28px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.35s ease;
        }

        .dark .featured-hero-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.85) 100%);
            border: 1.5px solid rgba(99, 102, 241, 0.35);
        }

        :not(.dark) .featured-hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1.5px solid rgba(99, 102, 241, 0.25);
        }

        .featured-hero-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #6366f1, #3b82f6, #10b981);
        }

        .institution-logo-container {
            width: 88px;
            height: 88px;
            border-radius: 22px;
            background: #ffffff;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            border: 1.5px solid rgba(226, 232, 240, 0.8);
            flex-shrink: 0;
        }

        .institution-logo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Metric Pill */
        .metric-pill {
            background: var(--bg-page-subtle);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 0.9rem 1.2rem;
            transition: all 0.25s ease;
        }

        .metric-pill:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .metric-value {
            font-family: var(--font-display);
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
        }

        .metric-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 0.25rem;
        }

        /* Unit Cards */
        .unit-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 1.75rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .unit-card:hover {
            transform: translateY(-6px);
            border-color: var(--card-hover-border);
            box-shadow: var(--card-shadow-hover);
        }

        .unit-card-header {
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
            margin-bottom: 1.25rem;
        }

        .unit-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: #ffffff;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(226, 232, 240, 0.9);
            flex-shrink: 0;
        }

        .unit-info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem 0;
            font-size: 0.875rem;
        }

        .unit-info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 0.65rem;
            color: var(--text-body);
        }

        .unit-info-item i {
            color: var(--primary);
            font-size: 1rem;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        .unit-info-item svg {
            color: var(--primary);
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        /* Footer */
        .welcome-footer {
            background: var(--footer-bg);
            color: var(--footer-text);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 3.5rem 0 2rem 0;
            margin-top: 5rem;
        }

        .footer-heading {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            letter-spacing: -0.01em;
        }

        .footer-link {
            color: var(--footer-text);
            text-decoration: none;
            font-size: 0.88rem;
            display: block;
            margin-bottom: 0.6rem;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: #ffffff;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-animated {
            animation: fadeInDown 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Responsive tweaks */
        @media (max-width: 768px) {
            .featured-hero-card {
                padding: 1.5rem !important;
                border-radius: 20px;
            }
            .institution-logo-container {
                width: 72px;
                height: 72px;
            }
            .metric-value {
                font-size: 1.4rem;
            }
        }
    </style>

    @livewireStyles
</head>

<body x-data="{
    theme: localStorage.getItem('theme') || 'dark',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        if (this.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}">

    {{-- Top Navigation Bar --}}
    <nav class="welcome-navbar py-2 px-3 md:px-4">
        <div class="container mx-auto px-4 max-w-7xl flex items-center justify-between">
            {{-- Brand Logo & Title --}}
            <a href="{{ url('/') }}" class="flex items-center no-underline gap-2 md:gap-3">
                <div class="bg-white rounded-2xl p-1 flex items-center justify-center shadow-sm" style="width: 42px; height: 42px; border: 1px solid rgba(0,0,0,0.06);">
                    <img src="{{ logo_url() }}" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <div>
                    <div class="navbar-brand-title flex items-center gap-2">
                        <span>{{ config('local.app.nama') ?? 'SIMBA' }}</span>
                    </div>
                    <div class="navbar-brand-subtitle hidden md:block">
                        {{ config('local.app.keterangan') ?? 'Sistem Informasi Lembaga Pendidikan Miftahul Ulum' }}
                    </div>
                </div>
            </a>

            {{-- Nav Links --}}
            <div class="hidden lg:flex items-center gap-1">
                <a href="#profil-yayasan" class="nav-link-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg> Yayasan
                </a>
                <a href="#satuan-pendidikan" class="nav-link-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg> Satuan Pendidikan
                </a>
                <a href="#keunggulan" class="nav-link-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg> Keunggulan
                </a>
                <a href="#kontak" class="nav-link-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg> Lokasi & Kontak
                </a>
            </div>

            {{-- Right CTA & Theme Toggle --}}
            <div class="flex items-center gap-2">
                {{-- Theme Switcher Button --}}
                <button type="button" @click="toggleTheme()" class="btn-theme-toggle" title="Ganti Tema">
                    <template x-if="theme === 'dark'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-yellow-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM18.894 6.166a.75.75 0 0 0-1.06-1.06l-1.591 1.59a.75.75 0 1 0 1.06 1.061l1.591-1.59ZM21.75 12a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H21a.75.75 0 0 1 .75.75ZM17.834 18.894a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 1 0-1.061 1.06l1.59 1.591ZM12 18a.75.75 0 0 1 .75.75V21a.75.75 0 0 1-1.5 0v-2.25A.75.75 0 0 1 12 18ZM7.758 17.303a.75.75 0 0 0-1.061-1.06l-1.591 1.59a.75.75 0 0 0 1.06 1.061l1.592-1.59ZM6 12a.75.75 0 0 1-.75.75H3a.75.75 0 0 1 0-1.5h2.25A.75.75 0 0 1 6 12ZM6.697 7.757a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 0 0-1.061 1.06l1.59 1.592Z" /></svg>
                    </template>
                    <template x-if="theme !== 'dark'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-600" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" /></svg>
                    </template>
                    <span class="hidden sm:inline" x-text="theme === 'dark' ? 'Terang' : 'Gelap'"></span>
                </button>

                {{-- Login / Dashboard Button --}}
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary-custom">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                        <span>Buka Dashboard</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary-custom">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                        <span>Masuk ke Sistem</span>
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Main Page Slot --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    @php
        $footerLembaga = \App\Models\Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];
        $footerYayasan = $footerLembaga[99] ?? (config('local.lembaga.99') ?? reset($footerLembaga));
    @endphp
    <footer class="welcome-footer" id="kontak">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                {{-- Info Yayasan --}}
                <div class="lg:col-span-1">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="bg-white rounded-xl p-1 flex items-center justify-center" style="width: 48px; height: 48px;">
                            <img src="{{ logo_url() }}" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <div>
                            <h5 class="mb-0 text-white font-bold">{{ $footerYayasan['nama'] ?? 'Yayasan' }}</h5>
                            <small class="text-slate-400">{{ config('local.app.keterangan') ?? (setting('nama_aplikasi') ?? 'Penyelenggara Lembaga Pendidikan') }}</small>
                        </div>
                    </div>
                    <p class="text-sm text-slate-400 mb-3 lg:pr-4">
                        Wadah pendidikan Islam terpadu yang membina generasi beriman, berilmu, dan berakhlak mulia melalui seluruh satuan pendidikan naungan yayasan.
                    </p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 border border-slate-600 text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-emerald-400" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> Terakreditasi & Resmi
                        </span>
                        @if (!empty($footerYayasan['alamat']))
                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 border border-slate-600 text-slate-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg> {{ \Illuminate\Support\Str::limit($footerYayasan['alamat'], 35) }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Satuan Pendidikan Naungan --}}
                <div>
                    <h6 class="footer-heading">Satuan Pendidikan</h6>
                    @foreach ($footerLembaga as $fid => $fitem)
                        @if ((int)$fid !== 99)
                            <div class="flex items-center justify-between mb-1">
                                <a href="#satuan-pendidikan" class="footer-link mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3 text-sky-500 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg> {{ $fitem['nama'] ?? $fitem['singkatan'] ?? 'Unit ' . $fid }}
                                </a>
                                @if (!empty($fitem['website']))
                                    <a href="{{ str_starts_with($fitem['website'], 'http') ? $fitem['website'] : 'https://' . $fitem['website'] }}" target="_blank" rel="noopener" class="text-slate-500 hover:text-slate-300 text-sm ml-1" title="Website {{ $fitem['singkatan'] ?? '' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endforeach
                    @if (isset($footerLembaga[99]))
                        <a href="#profil-yayasan" class="footer-link">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3 text-sky-500 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg> {{ !empty($footerLembaga[99]['singkatan']) ? $footerLembaga[99]['singkatan'] . ' - ' : '' }}Kantor Yayasan
                        </a>
                    @endif
                </div>

                {{-- Layanan & Akses --}}
                <div>
                    <h6 class="footer-heading">Akses SIMBA & Kontak</h6>
                    @if (!empty($footerYayasan['alamat']))
                        <div class="text-sm text-slate-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3.5 text-sky-500 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>{{ $footerYayasan['alamat'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['kontak']) || !empty($footerYayasan['telp']))
                        <div class="text-sm text-slate-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3.5 text-sky-500 mr-1.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>{{ $footerYayasan['kontak'] ?: $footerYayasan['telp'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['email']))
                        <div class="text-sm text-slate-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3.5 text-sky-500 mr-1.5" viewBox="0 0 24 24" fill="currentColor"><path d="M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z" /><path d="M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z" /></svg>{{ $footerYayasan['email'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['website']))
                        <div class="text-sm text-slate-400 mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline size-3.5 text-sky-500 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                            <a href="{{ str_starts_with($footerYayasan['website'], 'http') ? $footerYayasan['website'] : 'https://' . $footerYayasan['website'] }}" target="_blank" rel="noopener" class="text-slate-400 hover:text-white no-underline">
                                {{ $footerYayasan['website'] }}
                            </a>
                        </div>
                    @else
                        <div class="mb-3"></div>
                    @endif
                    <div>
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 border border-slate-500 text-slate-300 hover:bg-slate-700 hover:text-white font-semibold px-4 py-2 rounded-full transition text-sm no-underline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg> Portal Guru & Tenaga Kependidikan
                        </a>
                    </div>
                </div>
            </div>

            <hr class="border-slate-700 opacity-25 my-6">

            <div class="flex flex-col md:flex-row items-center justify-between gap-2 text-sm text-slate-500">
                <div>
                    &copy; {{ date('Y') }} <strong>{{ setting('nama_aplikasi') ?? config('local.app.nama') ?? 'SIMBA' }}</strong>. {{ $footerYayasan['nama'] ?? 'Yayasan' }}. Hak cipta dilindungi.
                </div>
                <div class="flex gap-3">
                    <a href="#profil-yayasan" class="text-slate-500 hover:text-white no-underline">Kembali ke Atas
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline size-4 ml-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 11.25l-3-3m0 0l-3 3m3-3v7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>

</html>
