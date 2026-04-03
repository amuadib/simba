<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Login</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('bootstrap-icons') }}" rel="stylesheet">

    <style>
        /* ======================
           THEME VARIABLES
        ====================== */
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

        /* ======================
           GLOBAL
        ====================== */
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

        .form-control::placeholder {
            color: var(--muted-text);
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

    <script>
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('btnLogin');
        const errorBox = document.getElementById('loginError');

        let retried = false;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorBox.classList.add('d-none');
            btn.disabled = true;

            const formData = new FormData(form);

            try {
                const res = await fetch("{{ route('login') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                if (res.status === 419 && !retried) {
                    retried = true;
                    await refreshToken();
                    return form.dispatchEvent(new Event('submit'));
                }

                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(text || 'Login gagal');
                }

                window.location.href = "{{ route('dashboard') }}";

            } catch (err) {
                errorBox.textContent = 'Login gagal. Periksa email atau password.';
                errorBox.classList.remove('d-none');
            } finally {
                btn.disabled = false;
            }
        });

        async function refreshToken() {
            const res = await fetch('/csrf-refresh');
            const data = await res.json();
            document.querySelector('meta[name="csrf-token"]').content = data.token;
        }
    </script>

</head>

<body class="d-flex align-items-center" data-theme="light">

    <div class="login-box w-100">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <h4 class="fw-semibold mb-1 text-center">
                    {{ setting('nama_aplikasi') ?? env('APP_NAME') }}
                </h4>
                <p class="text-muted mb-4 text-center">
                    Silakan login
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger small py-2">
                        {{ $errors->first() }}
                    </div>
                @endif
                <div id="loginError" class="text-danger d-none mt-2"></div>
                <form method="POST" action="/login" id="loginForm">
                    @csrf

                    {{-- Email --}}
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email"
                            value="{{ old('email') }}" required autofocus inputmode="email">
                    </div>

                    {{-- Password --}}
                    <div class="mb-3">
                        <label class="form-label">Password</label>

                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Password" required>

                            <span class="input-group-text password-toggle" id="togglePassword">
                                <span class="bi">🐵</span>
                            </span>
                        </div>
                    </div>

                    {{-- Remember --}}
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">
                            Ingat saya
                        </label>
                    </div>

                    {{-- Button --}}
                    <button class="btn btn-primary w-100 fw-semibold" id="btnLogin">
                        Login
                    </button>
                </form>

            </div>
        </div>

        <p class="text-muted small mt-3 text-center">
            © {{ date('Y') }} {{ setting('nama_lembaga') ?? env('APP_NAME') }}
        </p>
    </div>

    {{-- Toggle Password --}}
    <script>
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        toggle.addEventListener('click', () => {
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';

            toggle.innerHTML = isPassword ?
                '<span class="bi">🙈</span>' :
                '<span class="bi">🐵</span>';
        });
    </script>
    <script>
        const body = document.body;

        const applyTheme = () => {
            const theme = body.getAttribute('data-theme') || 'light';
            body.setAttribute('data-theme', theme);
        };

        // initial
        applyTheme();

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) body.setAttribute('data-theme', savedTheme);
    </script>
</body>

</html>
