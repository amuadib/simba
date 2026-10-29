@section('title', 'Manajemen Pengguna')
<?php

use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    // Form fields
    public string $name     = '';
    public string $email    = '';
    public string $role     = 'guru';
    public string $password = '';
    public string $password_confirmation = '';

    // Edit state
    public ?int $editingId = null;
    public string $action = ''; // '', 'create', 'edit'

    public function mount()
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function rules(): array
    {
        $passwordRule = $this->action === 'create'
            ? 'required|min:6|confirmed'
            : 'nullable|min:6|confirmed';

        $emailRule = $this->action === 'create'
            ? 'required|email|unique:users,email'
            : 'required|email|unique:users,email,' . $this->editingId;

        return [
            'name'     => 'required|string|max:255',
            'email'    => $emailRule,
            'role'     => 'required|in:admin,guru,siswa',
            'password' => $passwordRule,
        ];
    }

    public function store(): void
    {
        $this->validate();

        User::create([
            'name'     => $this->name,
            'email'    => $this->email,
            'role'     => $this->role,
            'password' => Hash::make($this->password),
        ]);

        $this->resetForm();
        $this->dispatch('toast', message: 'Pengguna berhasil ditambahkan', type: 'success');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->action = 'create';
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->action    = 'edit';
        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->role      = $user->role;
        $this->password  = '';
        $this->password_confirmation = '';
    }

    public function update(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingId);
        $data = [
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $user->update($data);
        $this->resetForm();
        $this->dispatch('toast', message: 'Pengguna berhasil diperbarui', type: 'success');
    }

    public function delete(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('toast', message: 'Tidak dapat menghapus akun sendiri', type: 'error');
            return;
        }

        User::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Pengguna berhasil dihapus', type: 'success');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'editingId']);
        $this->role = 'guru';
        $this->action = '';
    }

    public function with(): array
    {
        return [
            'users' => User::with(['pegawai', 'siswa'])->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Manajemen Pengguna</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola data dan hak akses akun pengguna sistem</p>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div>
            {{-- FORM AREA --}}
            @if($action === 'create' || $action === 'edit')
                <div class="p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
                    <h6 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                        <i class="bi {{ $action === 'edit' ? 'bi-pencil-square text-amber-500' : 'bi-person-plus text-sky-500' }}"></i>
                        <span>{{ $action === 'edit' ? 'Edit' : 'Tambah' }} Data Pengguna</span>
                    </h6>

                    <form wire:submit="{{ $action === 'edit' ? 'update' : 'store' }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Lengkap</label>
                            <input wire:model="name" type="text"
                                class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('name') is-invalid @enderror"
                                placeholder="Nama Lengkap">
                            @error('name')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Email</label>
                            <input wire:model="email" type="email"
                                class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('email') is-invalid @enderror"
                                placeholder="email@contoh.com">
                            @error('email')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Role</label>
                            <select wire:model="role"
                                class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('role') is-invalid @enderror">
                                <option value="guru">Guru</option>
                                <option value="admin">Admin</option>
                                <option value="siswa">Siswa</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">
                                Password
                                @if($action === 'edit')
                                    <span class="text-slate-400 font-normal">(Opsional)</span>
                                @endif
                            </label>
                            <input wire:model="password" type="password"
                                class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('password') is-invalid @enderror"
                                placeholder="{{ $action === 'edit' ? 'Kosong = tetap' : 'Password Baru' }}">
                            @error('password')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Konfirmasi Password</label>
                            <input wire:model="password_confirmation" type="password"
                                class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs"
                                placeholder="Ulangi Password">
                        </div>

                        <div class="col-12 flex items-center gap-2 pt-2">
                            <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                                <i class="bi bi-check2-circle text-sm"></i> Simpan
                            </button>
                            <button type="button" wire:click="cancelEdit"
                                class="btn btn-outline-secondary btn-sm rounded-xl px-4 py-2 text-xs font-semibold">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="flex justify-between items-center mb-4">
                    <button wire:click="create" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                        <i class="bi bi-plus-circle text-sm"></i> Tambah Pengguna
                    </button>
                </div>
            @endif

            {{-- TABLE --}}
            <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem; min-width: 620px;">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">Nama</th>
                            <th class="py-3 px-3">Email</th>
                            <th class="text-center py-3 px-3">Role</th>
                            <th class="text-center py-3 px-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $idx => $u)
                            <tr wire:key="{{ $u->id }}">
                                <td class="text-slate-400 py-3 px-3">{{ $idx + 1 }}</td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $u->name }}</div>
                                    @if($u->id === auth()->id())
                                        <span class="text-[10px] font-bold text-sky-500 dark:text-cyan-400">(Anda)</span>
                                    @endif
                                    @if($u->pegawai)
                                        <div class="text-xs text-slate-400 mt-0.5"><i class="bi bi-person-badge text-sky-500 me-1"></i>Pegawai: {{ $u->pegawai->nama }}</div>
                                    @elseif($u->siswa)
                                        <div class="text-xs text-slate-400 mt-0.5"><i class="bi bi-mortarboard text-cyan-500 me-1"></i>Siswa: {{ $u->siswa->nama }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-slate-600 dark:text-slate-300">{{ $u->email }}</td>
                                <td class="text-center py-3 px-3">
                                    @if($u->role === 'admin')
                                        <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                                            <i class="bi bi-shield-check me-1"></i>Admin
                                        </span>
                                    @elseif($u->role === 'siswa')
                                        <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-500/30">
                                            <i class="bi bi-mortarboard me-1"></i>Siswa
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                            <i class="bi bi-person me-1"></i>Guru
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center py-3 px-3">
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="edit({{ $u->id }})"
                                            class="btn btn-sm btn-outline-warning rounded-lg" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if($u->id !== auth()->id())
                                            <button wire:click="delete({{ $u->id }})"
                                                wire:confirm="Hapus pengguna {{ $u->name }}?"
                                                class="btn btn-sm btn-outline-danger rounded-lg ms-1" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary rounded-lg ms-1" disabled title="Tidak bisa menghapus akun sendiri">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-slate-400 py-5">
                                    <i class="bi bi-people text-4xl block mb-2 opacity-50"></i>
                                    Belum ada pengguna
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
