<?php

use App\Models\Rombel;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $tingkat = 0;
    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
        ]);

        Rombel::create([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
        ]);

        $this->reset(['nama', 'tingkat']);
        $this->dispatch('toast', message: 'Rombel berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $rombel = Rombel::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $rombel->nama;
        $this->tingkat = $rombel->tingkat;
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
        ]);

        $rombel = Rombel::findOrFail($this->editingId);
        $rombel->update([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
        ]);

        $this->reset(['nama', 'tingkat', 'editingId']);
        $this->dispatch('toast', message: 'Rombel berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'tingkat', 'editingId']);
    }

    public function delete($id)
    {
        Rombel::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Rombel berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'rombels' => Rombel::orderBy('tingkat')->orderBy('nama')->paginate(15),
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Rombel</h4>
            <small class="text-muted">Daftar seluruh Rombel</small>
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
                            placeholder="Nama Rombel" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col">
                        <select wire:model="tingkat" class="form-control @error('tingkat') is-invalid @enderror">
                            <option value="0">--Pilih Tingkat--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('tingkat')
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
                            placeholder="Nama Rombel Baru" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col">
                        <select wire:model="tingkat" class="form-control @error('tingkat') is-invalid @enderror">
                            <option value="0">--Pilih Tingkat--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('tingkat')
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
                            <th>Tingkat</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rombels as $r)
                            <tr wire:key="{{ $r->id }}">
                                <td>{{ $r->nama }}</td>
                                <td>{{ $r->tingkat }}</td>
                                <td>
                                    <button wire:click="edit('{{ $r->id }}')"
                                        class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button wire:click="delete('{{ $r->id }}')" wire:confirm="Hapus data ini?"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">Tidak ada data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $rombels->links() }}
            </div>
        </div>
    </div>
</div>
