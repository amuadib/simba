@section('title', 'Data Pelajaran')
<?php

use App\Models\Pelajaran;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate(['nama' => 'required']);
        Pelajaran::create(['nama' => $this->nama]);
        $this->reset('nama');
        $this->dispatch('toast', message: 'Pelajaran berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $pelajaran = Pelajaran::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $pelajaran->nama;
    }

    public function update()
    {
        $this->validate(['nama' => 'required']);
        $pelajaran = Pelajaran::findOrFail($this->editingId);
        $pelajaran->update(['nama' => $this->nama]);

        $this->reset(['nama', 'editingId']);
        $this->dispatch('toast', message: 'Pelajaran berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'editingId']);
    }

    public function delete($id)
    {
        Pelajaran::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Pelajaran berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'pelajaran' => Pelajaran::orderBy('nama')->paginate(15),
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Pelajaran</h4>
            <small class="text-muted">Daftar seluruh Pelajaran</small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($editingId)
                <form wire:submit="update" class="row g-2 mb-3">
                    <div class="col">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Pelajaran" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle"></i>
                            Update</button>
                        <button type="button" wire:click="cancelEdit" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 mb-3">
                    <div class="col">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Pelajaran Baru" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table-bordered table">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pelajaran as $p)
                            <tr wire:key="{{ $p->id }}">
                                <td>{{ $p->nama }}</td>
                                <td>
                                    <button wire:click="edit('{{ $p->id }}')"
                                        class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button wire:click="delete('{{ $p->id }}')" wire:confirm="Hapus data ini?"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center">Tidak ada data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $pelajaran->links() }}
            </div>
        </div>
    </div>
</div>
