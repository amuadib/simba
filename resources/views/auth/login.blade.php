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
        body {
            background: linear-gradient(135deg, #e9f0ff, #f8f9fa);
            min-height: 100vh;
        }

        .login-box {
            max-width: 420px;
            margin: auto;
            padding: 1rem;
        }

        .form-control,
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

<body class="d-flex align-items-center">

    <div class="login-box w-100">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <h4 class="fw-semibold mb-1 text-center">
                    Sistem Presensi
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
            © {{ date('Y') }} Sistem Presensi
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

</body>

</html>
