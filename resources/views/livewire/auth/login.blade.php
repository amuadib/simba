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
            'email' => 'Email atau kata sandi tidak cocok dengan data kami',
        ]);
    }
};

?>

<div class="w-full">
    {{-- Card Container with Glassmorphism and Logo Glow Border --}}
    <div class="relative glass-panel rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200/90 dark:border-cyan-500/20">
        
        {{-- Card Header --}}
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-500/20 via-cyan-500/15 to-emerald-500/20 p-2.5 ring-2 ring-cyan-500/40 shadow-lg shadow-cyan-500/20 mb-3 group hover:scale-105 transition-transform">
                <img src="{{ logo_url() }}" alt="Logo" class="w-full h-full object-contain">
            </div>
            
            <h2 class="text-2xl font-extrabold font-display text-gradient-logo mb-1">
                {{ setting('nama_aplikasi') ?? env('APP_NAME') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Silakan masuk dengan akun terdaftar untuk melanjutkan
            </p>
        </div>

        {{-- Form Login --}}
        <form wire:submit="login" class="space-y-4">
            
            {{-- Email --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5 font-display">
                    Alamat Email
                </label>
                <div class="relative flex items-center">
                    <span class="absolute left-3.5 text-cyan-600 dark:text-cyan-400 text-sm pointer-events-none">
                        <i class="bi bi-envelope-at"></i>
                    </span>
                    <input type="email" 
                           wire:model="email" 
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-cyan-500/25 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/30 focus:border-cyan-500 transition-all @error('email') border-rose-500 @enderror"
                           placeholder="nama@email.com" 
                           required 
                           autofocus 
                           inputmode="email">
                </div>
                @error('email') 
                    <span class="text-xs text-rose-500 mt-1 block font-medium">{{ $message }}</span> 
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5 font-display">
                    Kata Sandi
                </label>
                <div class="relative flex items-center">
                    <span class="absolute left-3.5 text-cyan-600 dark:text-cyan-400 text-sm pointer-events-none">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <input type="password" 
                           wire:model="password" 
                           id="passwordInput"
                           class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-cyan-500/25 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/30 focus:border-cyan-500 transition-all @error('password') border-rose-500 @enderror"
                           placeholder="••••••••" 
                           required>
                    <button type="button" 
                            onclick="togglePasswordVisibility()" 
                            class="absolute right-3.5 text-slate-400 hover:text-cyan-500 text-sm transition-colors"
                            title="Tampilkan / Sembunyikan Sandi">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>
                @error('password') 
                    <span class="text-xs text-rose-500 mt-1 block font-medium">{{ $message }}</span> 
                @enderror
            </div>

            {{-- Remember Me & Info --}}
            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center gap-2 cursor-pointer text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    <span>Ingat saya di perangkat ini</span>
                </label>
                <span class="text-slate-400 text-[11px]">Butuh bantuan? Hubungi Admin</span>
            </div>

            {{-- Submit Button --}}
            <div class="pt-2">
                <button type="submit" 
                        class="w-full py-3 px-4 rounded-xl btn-logo-gradient flex items-center justify-center gap-2 text-sm font-bold shadow-lg"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove class="flex items-center gap-2">
                        <span>Masuk ke SIMBA</span>
                        <i class="bi bi-arrow-right"></i>
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        <span>Memverifikasi Akun...</span>
                    </span>
                </button>
            </div>
        </form>

        {{-- Footer Note --}}
        <div class="mt-6 pt-4 border-t border-slate-200/70 dark:border-slate-800/70 text-center">
            <p class="text-[11px] text-slate-400 m-0">
                &copy; {{ date('Y') }} {{ config('local.lembaga.99.nama') ?? env('APP_NAME') }} • Hak Cipta Dilindungi
            </p>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pwd = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            if (!pwd || !icon) return;
            const isPassword = pwd.type === 'password';
            pwd.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
        }
    </script>
</div>
