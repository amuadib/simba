<?php

use App\Models\Jurnal;
use App\Models\Pembelajaran;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $pembelajaran;
    public $tanggal = '';
    public $materi = '';

    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->pembelajaran = $pembelajaran;
        $this->tanggal = date('Y-m-d');
    }

    public function store()
    {
        $this->validate([
            'tanggal' => 'required|date',
            'materi' => 'required',
        ]);

        Jurnal::create([
            'pembelajaran_id' => $this->pembelajaran->id,
            'tanggal' => $this->tanggal,
            'materi' => $this->materi,
        ]);

        $this->reset(['materi']);
        $this->tanggal = date('Y-m-d');
        $this->dispatch('toast', message: 'Jurnal berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $jurnal = Jurnal::find($id);
        $this->editingId = $id;
        $this->tanggal = $jurnal->tanggal;
        $this->materi = $jurnal->materi;
    }

    public function update()
    {
        $this->validate([
            'tanggal' => 'required|date',
            'materi' => 'required',
        ]);

        $jurnal = Jurnal::find($this->editingId);
        $jurnal->update([
            'tanggal' => $this->tanggal,
            'materi' => $this->materi,
        ]);

        $this->reset(['editingId', 'materi']);
        $this->tanggal = date('Y-m-d');
        $this->dispatch('toast', message: 'Jurnal berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['editingId', 'tanggal', 'materi']);
    }

    public function delete($id)
    {
        Jurnal::find($id)->delete();
        $this->dispatch('toast', message: 'Jurnal berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'jurnals' => Jurnal::where('pembelajaran_id', $this->pembelajaran->id)->orderByDesc('tanggal')->paginate(10),
        ];
    }
};

?>
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Jurnal Pembelajaran</h4>
            <small class="text-muted">Dokumentasi KBM: <strong>{{ $pembelajaran->keterangan }}</strong></small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="{{ route('pembelajaran.jurnal.nilai.index', $pembelajaran) }}" wire:navigate
                    class="btn btn-primary">
                    <i class="bi bi-star me-1"></i> Daftar Nilai
                </a>
            </div>

            @if ($editingId)
                <form wire:submit="update" class="row g-2 mb-3">
                    <div class="col-sm-2">
                        <input wire:model="tanggal" class="form-control @error('tanggal') is-invalid @enderror"
                            placeholder="Tanggal" required>
                        @error('tanggal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-sm-8">
                        <input wire:model="materi" class="form-control @error('materi') is-invalid @enderror"
                            placeholder="Materi" required>
                        @error('materi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle"></i>
                            Update</button>
                        <button type="button" wire:click="cancelEdit" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 mb-3">
                    <div class="col-sm-2">
                        <input wire:model="tanggal" class="form-control @error('tanggal') is-invalid @enderror"
                            placeholder="Tanggal" required>
                        @error('tanggal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-sm-8">
                        <input wire:model="materi" class="form-control @error('materi') is-invalid @enderror"
                            placeholder="Materi" required>
                        @error('materi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table-hover table border align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="200">Tanggal</th>
                            <th>Materi Pembelajaran</th>
                            <th width="200" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jurnals as $j)
                            <tr wire:key="{{ $j->id }}">
                                <td>
                                    {{ \Carbon\Carbon::parse($j->tanggal)->locale('id_ID')->isoFormat('DD MMMM YYYY') }}
                                </td>
                                <td>
                                    {!! nl2br(e($j->materi)) !!}
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="{{ route('pembelajaran.jurnal.nilai.create', [$pembelajaran->id, $j->id]) }}"
                                            wire:navigate class="btn btn-sm btn-outline-primary"
                                            title="Input Nilai & Presensi">
                                            <i class="bi bi-pencil-square me-1"></i> Nilai
                                        </a>
                                        <button wire:click="edit('{{ $j->id }}')"
                                            class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button wire:click="delete('{{ $j->id }}')"
                                            wire:confirm="Hapus jurnal ini?" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted small py-4 text-center">Belum ada jurnal
                                    pembelajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $jurnals->links() }}
            </div>
        </div>
    </div>
</div>
