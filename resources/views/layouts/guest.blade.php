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
        body {
            --bg-gradient: linear-gradient(135deg, #e9f0ff, #f8f9fa);
            --card-bg: #ffffff;
            --text-color: #212529;
            --muted-text: #6c757d;
            --input-bg: #ffffff;
            --input-border: #ced4da;
        }

        body[data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #0f172a, #020617);
            --card-bg: #020617;
            --text-color: #e5e7eb;
            --muted-text: #9ca3af;
            --input-bg: #020617;
            --input-border: #1e293b;
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-color);
            min-height: 100vh;
        }

        .login-box {
            max-width: 420px;
            margin: auto;
            padding: 1rem;
        }

        .card {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .text-muted {
            color: var(--muted-text) !important;
        }

        .form-control,
        .form-check-input,
        .input-group-text {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-color);
        }

        .form-control:focus {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: #3b82f6;
            box-shadow: none;
        }

        .btn {
            min-height: 48px;
            font-size: 1rem;
        }

        .password-toggle {
            cursor: pointer;
        }
    </style>
    @livewireStyles
</head>

<body class="d-flex align-items-center" data-theme="light">

    {{ $slot }}

    @livewireScripts
    <script>
        const body = document.body;
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) body.setAttribute('data-theme', savedTheme);
    </script>
</body>

</html>
