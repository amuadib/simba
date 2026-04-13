<?php

use function Livewire\Volt\{state, rules, layout};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

state([
    'email' => '',
    'password' => '',
    'remember' => false,
]);

rules([
    'email' => 'required|email',
    'password' => 'required',
]);

$login = function () {
    $this->validate();

    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    throw ValidationException::withMessages([
        'email' => 'Email atau password salah',
    ]);
};

layout('layouts.guest');

?>

<div class="login-box w-100">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">

            <h4 class="fw-semibold mb-1 text-center">
                {{ setting('nama_aplikasi') ?? env('APP_NAME') }}
            </h4>
            <p class="text-muted mb-4 text-center">
                Silakan login
            </p>

            <form wire:submit="login">
                {{-- Email --}}
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email"
                        required autofocus inputmode="email">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Password --}}
                <div class="mb-3">
                    <label class="form-label">Password</label>

                    <div class="input-group">
                        <input type="password" wire:model="password" id="password" class="form-control @error('password') is-invalid @enderror"
                            placeholder="Password" required>
                        <span class="input-group-text password-toggle" id="togglePassword" onclick="togglePasswordVisibility()">
                            <span class="bi" id="toggleIcon">🐵</span>
                        </span>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Remember --}}
                <div class="form-check mb-3">
                    <input type="checkbox" wire:model="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">
                        Ingat saya
                    </label>
                </div>

                {{-- Button --}}
                <button type="submit" class="btn btn-primary w-100 fw-semibold" wire:loading.attr="disabled">
                    <span wire:loading.remove>Login</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </form>

        </div>
    </div>

    <p class="text-muted small mt-3 text-center">
        © {{ date('Y') }} {{ setting('nama_lembaga') ?? env('APP_NAME') }}
    </p>

    <script>
        function togglePasswordVisibility() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';
            icon.textContent = isPassword ? '🙈' : '🐵';
        }
    </script>
</div>
