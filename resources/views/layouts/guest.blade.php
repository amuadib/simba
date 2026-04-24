<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Login' }} - {{ setting('nama_aplikasi') ?? env('APP_NAME') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.8);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --accent: #3b82f6;
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
        }

        [data-theme="dark"] {
            --bg-main: #020617;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-main: #ffffff;
            --text-muted: #cbd5e1;
            --accent: #60a5fa;
            --input-bg: #0f172a;
            --input-border: #334155;
        }

        body, h1, h2, h3, h4, h5, h6, label {
            color: var(--text-main) !important;
        }

        body {
            background-color: var(--bg-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.15) 0px, transparent 50%);
            transition: all 0.3s ease;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            z-index: 1;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .form-control {
            background-color: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--text-main) !important;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background-color: var(--input-bg);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            color: var(--text-main) !important;
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        .input-group-text {
            background-color: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--accent);
        }

        .form-control.with-group {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .btn-primary {
            background-color: var(--accent);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4);
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .password-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--accent);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-box {
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .logo-container {
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .logo-container {
            background-color: rgba(255, 255, 255, 0.9) !important;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4) !important;
        }
    </style>
    @livewireStyles
</head>

<body x-data="{ theme: localStorage.getItem('theme') || 'dark' }" :data-theme="theme">

    {{ $slot }}

    @livewireScripts
</body>

</html>

</html>
