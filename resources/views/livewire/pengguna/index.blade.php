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
            'role'     => 'required|in:admin,guru',
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
            'users' => User::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Manajemen Pengguna</h4>
            <small class="text-muted">Kelola akun pengguna sistem</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            {{-- FORM AREA --}}
            @if($action === 'create' || $action === 'edit')
                <div class="bg-light mb-4 rounded border p-3">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="bi {{ $action === 'edit' ? 'bi-pencil-square' : 'bi-person-plus' }} me-1"></i>
                        {{ $action === 'edit' ? 'Edit' : 'Tambah' }} Data Pengguna
                    </h6>

                    <form wire:submit="{{ $action === 'edit' ? 'update' : 'store' }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input wire:model="name" type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="Nama Lengkap">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Email</label>
                            <input wire:model="email" type="email"
                                class="form-control @error('email') is-invalid @enderror"
                                placeholder="email@contoh.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Role</label>
                            <select wire:model="role"
                                class="form-select @error('role') is-invalid @enderror">
                                <option value="guru">Guru</option>
                                <option value="admin">Admin</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">
                                Password
                                @if($action === 'edit')
                                    <small class="text-muted fw-normal">(Opsional)</small>
                                @endif
                            </label>
                            <input wire:model="password" type="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="{{ $action === 'edit' ? 'Kosong = tetap' : 'Password Baru' }}">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Konfirmasi Password</label>
                            <input wire:model="password_confirmation" type="password"
                                class="form-control"
                                placeholder="Ulangi Password">
                        </div>

                        <div class="col-12 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-{{ $action === 'edit' ? 'warning' : 'primary' }}">
                                <i class="bi bi-save me-1"></i> SIMPAN
                            </button>
                            <button type="button" wire:click="cancelEdit"
                                class="btn btn-secondary">
                                BATAL
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button wire:click="create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>
                        Tambah Pengguna</button>
                </div>
            @endif

            {{-- TABLE --}}
            <div class="table-responsive">
                <table class="table-hover table border align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $idx => $u)
                            <tr wire:key="{{ $u->id }}">
                                <td class="text-muted" style="font-size:.85rem;">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $u->name }}</div>
                                    @if($u->id === auth()->id())
                                        <span style="font-size:.72rem;color:#6366f1;">(Anda)</span>
                                    @endif
                                </td>
                                <td style="font-size:.875rem;">{{ $u->email }}</td>
                                <td class="text-center">
                                    @if($u->role === 'admin')
                                        <span class="badge"
                                            style="background:rgba(99,102,241,.15);color:#6366f1;font-size:.75rem;">
                                            <i class="bi bi-shield-check me-1"></i>Admin
                                        </span>
                                    @else
                                        <span class="badge"
                                            style="background:rgba(34,197,94,.12);color:#16a34a;font-size:.75rem;">
                                            <i class="bi bi-person me-1"></i>Guru
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="edit({{ $u->id }})"
                                            class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if($u->id !== auth()->id())
                                            <button wire:click="delete({{ $u->id }})"
                                                wire:confirm="Hapus pengguna {{ $u->name }}?"
                                                class="btn btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-outline-secondary" disabled title="Tidak bisa menghapus akun sendiri">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-2 d-block mb-2"></i>
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
