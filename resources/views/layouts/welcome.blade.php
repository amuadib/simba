<!doctype html>
<html lang="id">

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

    {{-- Local Assets --}}
    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">

    {{-- Tailwind CSS v4 via Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme immediately to prevent flashing
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
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

        [data-theme="dark"] {
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

        [data-theme="dark"] .featured-hero-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.85) 100%);
            border: 1.5px solid rgba(99, 102, 241, 0.35);
        }

        [data-theme="light"] .featured-hero-card {
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
        document.documentElement.setAttribute('data-theme', this.theme);
    }
}" :data-theme="theme">

    {{-- Top Navigation Bar --}}
    <nav class="welcome-navbar py-2 px-3 px-md-4">
        <div class="container-fluid max-w-7xl d-flex align-items-center justify-content-between">
            {{-- Brand Logo & Title --}}
            <a href="{{ url('/') }}" class="d-flex align-items-center text-decoration-none gap-2 gap-md-3">
                <div class="bg-white rounded-3 p-1 d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; border: 1px solid rgba(0,0,0,0.06);">
                    <img src="{{ logo_url() }}" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <div>
                    <div class="navbar-brand-title d-flex align-items-center gap-2">
                        <span>{{ setting('nama_aplikasi') ?? config('local.app.nama') ?? 'SIMBA' }}</span>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0 fs-6 fw-semibold d-none d-sm-inline" style="font-size: 0.7rem !important;">Portal Resmi</span>
                    </div>
                    <div class="navbar-brand-subtitle d-none d-md-block">
                        {{ config('local.app.keterangan') ?? 'Sistem Informasi Lembaga Pendidikan Miftahul Ulum' }}
                    </div>
                </div>
            </a>

            {{-- Nav Links --}}
            <div class="d-none d-lg-flex align-items-center gap-1">
                <a href="#profil-yayasan" class="nav-link-custom">
                    <i class="bi bi-shield-check me-1"></i> Yayasan
                </a>
                <a href="#satuan-pendidikan" class="nav-link-custom">
                    <i class="bi bi-buildings me-1"></i> Satuan Pendidikan
                </a>
                <a href="#keunggulan" class="nav-link-custom">
                    <i class="bi bi-stars me-1"></i> Keunggulan
                </a>
                <a href="#kontak" class="nav-link-custom">
                    <i class="bi bi-geo-alt me-1"></i> Lokasi & Kontak
                </a>
            </div>

            {{-- Right CTA & Theme Toggle --}}
            <div class="d-flex align-items-center gap-2">
                {{-- Theme Switcher Button --}}
                <button type="button" @click="toggleTheme()" class="btn-theme-toggle" title="Ganti Tema">
                    <i class="bi" :class="theme === 'dark' ? 'bi-sun-fill text-warning' : 'bi-moon-stars-fill text-primary'"></i>
                    <span class="d-none d-sm-inline" x-text="theme === 'dark' ? 'Terang' : 'Gelap'"></span>
                </button>

                {{-- Login / Dashboard Button --}}
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary-custom">
                        <i class="bi bi-speedometer2"></i>
                        <span>Buka Dashboard</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary-custom">
                        <i class="bi bi-box-arrow-in-right"></i>
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
        <div class="container">
            <div class="row g-4 mb-4">
                {{-- Info Yayasan --}}
                <div class="col-lg-5 col-md-6">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-white rounded-3 p-1 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <img src="{{ logo_url() }}" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <div>
                            <h5 class="mb-0 text-white fw-bold">{{ $footerYayasan['nama'] ?? 'Yayasan' }}</h5>
                            <small class="text-secondary">{{ config('local.app.keterangan') ?? (setting('nama_aplikasi') ?? 'Penyelenggara Lembaga Pendidikan') }}</small>
                        </div>
                    </div>
                    <p class="small text-secondary mb-3 pe-lg-4">
                        Wadah pendidikan Islam terpadu yang membina generasi beriman, berilmu, dan berakhlak mulia melalui seluruh satuan pendidikan naungan yayasan.
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-dark border border-secondary text-secondary px-3 py-2 rounded-pill small">
                            <i class="bi bi-shield-fill-check text-success me-1"></i> Terakreditasi & Resmi
                        </span>
                        @if (!empty($footerYayasan['alamat']))
                            <span class="badge bg-dark border border-secondary text-secondary px-3 py-2 rounded-pill small">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ \Illuminate\Support\Str::limit($footerYayasan['alamat'], 35) }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Satuan Pendidikan Naungan --}}
                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-heading">Satuan Pendidikan</h6>
                    @foreach ($footerLembaga as $fid => $fitem)
                        @if ((int)$fid !== 99)
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <a href="#satuan-pendidikan" class="footer-link mb-0">
                                    <i class="bi bi-chevron-right text-primary me-1 small"></i> {{ $fitem['nama'] ?? $fitem['singkatan'] ?? 'Unit ' . $fid }}
                                </a>
                                @if (!empty($fitem['website']))
                                    <a href="{{ str_starts_with($fitem['website'], 'http') ? $fitem['website'] : 'https://' . $fitem['website'] }}" target="_blank" rel="noopener" class="text-secondary text-opacity-75 small ms-1" title="Website {{ $fitem['singkatan'] ?? '' }}">
                                        <i class="bi bi-globe"></i>
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endforeach
                    @if (isset($footerLembaga[99]))
                        <a href="#profil-yayasan" class="footer-link">
                            <i class="bi bi-chevron-right text-primary me-1 small"></i> {{ !empty($footerLembaga[99]['singkatan']) ? $footerLembaga[99]['singkatan'] . ' - ' : '' }}Kantor Yayasan
                        </a>
                    @endif
                </div>

                {{-- Layanan & Akses --}}
                <div class="col-lg-4 col-md-6">
                    <h6 class="footer-heading">Akses SIMBA & Kontak</h6>
                    @if (!empty($footerYayasan['alamat']))
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-geo-alt text-primary me-2"></i>{{ $footerYayasan['alamat'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['kontak']) || !empty($footerYayasan['telp']))
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-telephone text-primary me-2"></i>{{ $footerYayasan['kontak'] ?: $footerYayasan['telp'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['email']))
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-envelope text-primary me-2"></i>{{ $footerYayasan['email'] }}
                        </div>
                    @endif
                    @if (!empty($footerYayasan['website']))
                        <div class="small text-secondary mb-3">
                            <i class="bi bi-globe text-primary me-2"></i>
                            <a href="{{ str_starts_with($footerYayasan['website'], 'http') ? $footerYayasan['website'] : 'https://' . $footerYayasan['website'] }}" target="_blank" rel="noopener" class="text-secondary text-decoration-none">
                                {{ $footerYayasan['website'] }}
                            </a>
                        </div>
                    @else
                        <div class="mb-3"></div>
                    @endif
                    <div>
                        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light rounded-pill px-3">
                            <i class="bi bi-lock me-1"></i> Portal Guru & Tenaga Kependidikan
                        </a>
                    </div>
                </div>
            </div>

            <hr class="border-secondary opacity-25 my-4">

            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 small text-secondary">
                <div>
                    &copy; {{ date('Y') }} <strong>{{ setting('nama_aplikasi') ?? config('local.app.nama') ?? 'SIMBA' }}</strong>. {{ $footerYayasan['nama'] ?? 'Yayasan' }}. Hak cipta dilindungi.
                </div>
                <div class="d-flex gap-3">
                    <a href="#profil-yayasan" class="text-secondary text-decoration-none">Kembali ke Atas <i class="bi bi-arrow-up-circle ms-1"></i></a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>

</html>
