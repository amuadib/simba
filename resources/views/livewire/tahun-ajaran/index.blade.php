@section('title','Data Tahun Ajaran')

<?php

use App\Models\TahunAjaran;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $aktif = 'n';
    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate([
            'nama' => 'required',
            'aktif' => 'required|in:y,n',
        ]);

        $ta = TahunAjaran::create([
            'nama' => $this->nama,
            'aktif' => $this->aktif,
        ]);

        if ($this->aktif == 'y') {
            //Reset Session Aktif
            session()->put('tahun_ajaran_id', $ta->id);
            session()->put('tahun_ajaran_nama', $ta->nama);
            TahunAjaran::where('id', '!=', $ta->id)->update(['aktif' => 'n']);
        }
        $this->reset(['nama', 'aktif']);
        $this->dispatch('toast', message: 'Tahun Ajaran berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $ta = TahunAjaran::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $ta->nama;
        $this->aktif = $ta->aktif;
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required',
            'aktif' => 'required|in:y,n',
        ]);

        $ta = TahunAjaran::findOrFail($this->editingId);
        $ta->update([
            'nama' => $this->nama,
            'aktif' => $this->aktif,
        ]);

        if ($this->aktif == 'y') {
            //Reset Session Aktif
            session()->put('tahun_ajaran_id', $ta->id);
            session()->put('tahun_ajaran_nama', $ta->nama);
            TahunAjaran::where('id', '!=', $ta->id)->update(['aktif' => 'n']);
        }
        $this->reset(['nama', 'aktif', 'editingId']);
        $this->dispatch('toast', message: 'Tahun Ajaran berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'aktif', 'editingId']);
    }

    public function delete($id)
    {
        TahunAjaran::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Tahun Ajaran berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'tahunajarans' => TahunAjaran::orderBy('nama', 'desc')->paginate(15),
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Tahun Ajaran</h4>
            <small class="text-muted">Kelola periode akademik aktif</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($editingId)
                <form wire:submit="update" class="row g-2 align-items-center mb-3">
                    <div class="col-md-4">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Tahun Ajaran" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-check-inline">
                            <input wire:model="aktif" class="form-check-input" type="radio" value="y"
                                id="edit_aktif_y">
                            <label class="form-check-label" for="edit_aktif_y">Aktif</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input wire:model="aktif" class="form-check-input" type="radio" value="n"
                                id="edit_aktif_n">
                            <label class="form-check-label" for="edit_aktif_n">Tidak Aktif</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle"></i>
                            Update</button>
                        <button type="button" wire:click="cancelEdit" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 align-items-center mb-3">
                    <div class="col-md-4">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Tahun Ajaran Baru" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-check-inline">
                            <input wire:model="aktif" class="form-check-input" type="radio" value="y"
                                id="aktif_y">
                            <label class="form-check-label" for="aktif_y">Aktif</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input wire:model="aktif" class="form-check-input" type="radio" value="n"
                                id="aktif_n">
                            <label class="form-check-label" for="aktif_n">Tidak Aktif</label>
                        </div>
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
                            <th>Aktif</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tahunajarans as $ta)
                            <tr wire:key="{{ $ta->id }}">
                                <td>{{ $ta->nama }}</td>
                                <td>{!! $ta->aktif == 'y'
                                    ? '<span class="text-success">✅ Aktif</span>'
                                    : '<span class="text-danger">🚫 Tidak Aktif</span>' !!}</td>
                                <td>
                                    <button wire:click="edit('{{ $ta->id }}')"
                                        class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button wire:click="delete('{{ $ta->id }}')" wire:confirm="Hapus data ini?"
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
                {{ $tahunajarans->links() }}
            </div>
        </div>
    </div>
</div>
