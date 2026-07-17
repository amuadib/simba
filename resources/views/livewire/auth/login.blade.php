<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.guest')] class extends Component {
    public $email = '';
    public $password = '';
    public $remember = false;

    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            $tahun_ajaran = \App\Models\TahunAjaran::where('aktif', 'y')->first();
            session()->put('tahun_ajaran_id', $tahun_ajaran->id);
            session()->put('tahun_ajaran_nama', $tahun_ajaran->nama);
            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'Email atau password salah',
        ]);
    }
};

?>

<div class="login-box">
    <div class="card border-0">
        <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
                <div class="d-inline-block p-3 rounded-circle bg-white shadow-sm mb-3 logo-container">
                    <img src="{{ logo_url() }}" alt="Logo" style="height: 60px; width: 60px; object-fit: contain;">
                </div>
                <h4 class="fw-bold mb-1">
                    {{ setting('nama_aplikasi') ?? env('APP_NAME') }}
                </h4>
            </div>

            <form wire:submit="login">
                {{-- Email --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Alamat Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" wire:model="email" class="form-control with-group @error('email') is-invalid @enderror" 
                            placeholder="nama@contoh.com" required autofocus inputmode="email">
                    </div>
                    @error('email') <div class="invalid-feedback d-block mt-1">{{ $message }}</div> @enderror
                </div>

                {{-- Password --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" wire:model="password" id="password" 
                            class="form-control with-group @error('password') is-invalid @enderror"
                            placeholder="••••••••" required>
                        <span class="input-group-text bg-transparent border-start-0 password-toggle" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                        </span>
                    </div>
                    @error('password') <div class="invalid-feedback d-block mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" wire:model="remember" class="form-check-input" id="remember">
                        <label class="form-check-label small" for="remember">
                            Ingat saya
                        </label>
                    </div>
                    <a href="#" class="small text-decoration-none fw-medium">Lupa sandi?</a>
                </div>

                {{-- Button --}}
                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" wire:loading.attr="disabled">
                    <span wire:loading.remove>Masuk ke Akun</span>
                    <span wire:loading class="spinner-border spinner-border-sm" role="status"></span>
                    <span wire:loading>Memproses...</span>
                </button>
            </form>

        </div>
    </div>

    <div class="text-center mt-4">
        <p class="text-muted small">
            &copy; {{ date('Y') }} {{ setting('nama_lembaga') ?? env('APP_NAME') }}
        </p>
    </div>

    <script>
        function togglePasswordVisibility() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
        }
    </script>
</div>
