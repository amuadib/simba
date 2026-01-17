<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap 5 --}}
    <link href="{{ asset('bootstrap.min.css') }}" rel="stylesheet">

    <style>
        body {
            background: #f5f7fa;
        }

        .login-box {
            max-width: 420px;
            margin: auto;
            margin-top: 12vh;
        }
    </style>
</head>

<body>

    <div class="login-box">
        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h4 class="mb-4 text-center">Login Sistem Presensi</h4>

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="/login">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required
                            autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>

                    <button class="btn btn-primary w-100">
                        Login
                    </button>
                </form>

            </div>
        </div>

        <p class="text-muted mt-3 text-center">
            © {{ date('Y') }} Sistem Presensi
        </p>
    </div>

</body>

</html>
