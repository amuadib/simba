<?php

use App\Models\Pembelajaran;
use App\Models\TahunAjaran;
use App\Models\Pelajaran;
use App\Models\Rombel;
use App\Models\Siswa;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $tahun_ajaran_id = '';
    public $pelajaran_id = '';
    public $kelas_id = '';
    public $keterangan = '';

    public $editingId = null;
    public $action = '';

    public $paginationTheme = 'bootstrap';

    public function updated($property)
    {
        if (in_array($property, ['pelajaran_id', 'kelas_id'])) {
            $this->generateKeterangan();
        }
    }

    public function generateKeterangan()
    {
        if ($this->editingId) {
            return;
        }

        $pName = $this->pelajaran_id ? Pelajaran::find($this->pelajaran_id)?->nama : '';
        $kName = $this->kelas_id ? Rombel::find($this->kelas_id)?->nama : '';

        $this->keterangan = trim($pName . ' ' . $kName);
    }

    public function store()
    {
        $this->validate([
            'tahun_ajaran_id' => 'required',
            'pelajaran_id' => 'required',
            'keterangan' => 'nullable',
        ]);

        $pembelajaran = Pembelajaran::create([
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'pelajaran_id' => $this->pelajaran_id,
            'keterangan' => $this->keterangan,
        ]);

        if ($this->kelas_id) {
            foreach (Siswa::where('rombel_id', $this->kelas_id)->get() as $siswa) {
                $pembelajaran->anggota()->create([
                    'siswa_id' => $siswa->id,
                ]);
            }
        }

        $this->reset(['tahun_ajaran_id', 'pelajaran_id', 'kelas_id', 'keterangan']);
        $this->dispatch('toast', message: 'Pembelajaran berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $p = Pembelajaran::findOrFail($id);
        $this->editingId = $id;
        $this->action = 'edit';
        $this->tahun_ajaran_id = $p->tahun_ajaran_id;
        $this->pelajaran_id = $p->pelajaran_id;
        $this->keterangan = $p->keterangan;
    }

    public function update()
    {
        $this->validate([
            'tahun_ajaran_id' => 'required',
            'pelajaran_id' => 'required',
            'keterangan' => 'nullable',
        ]);

        $p = Pembelajaran::findOrFail($this->editingId);
        $p->update([
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'pelajaran_id' => $this->pelajaran_id,
            'keterangan' => $this->keterangan,
        ]);

        $this->reset(['tahun_ajaran_id', 'pelajaran_id', 'kelas_id', 'keterangan', 'editingId', 'action']);
        $this->dispatch('toast', message: 'Pembelajaran berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['tahun_ajaran_id', 'pelajaran_id', 'kelas_id', 'keterangan', 'editingId', 'action']);
    }
    public function delete($id)
    {
        Pembelajaran::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Pembelajaran berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'pembelajarans' => Pembelajaran::with('tahunAjaran', 'pelajaran', 'anggota')->orderBy('keterangan', 'asc')->paginate(15),
            'tahunajarans' => TahunAjaran::orderBy('nama', 'desc')->get(),
            'pelajarans' => Pelajaran::orderBy('nama', 'desc')->get(),
            'rombels' => Rombel::orderBy('nama', 'desc')->get(),
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Pembelajaran</h4>
            <small class="text-muted">Kelola mata pelajaran dan pengampu</small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($action == 'edit')
                <form wire:submit="update" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tahun Ajaran</label>
                        <select wire:model="tahun_ajaran_id"
                            class="form-select @error('tahun_ajaran_id') is-invalid @enderror" required>
                            <option value="">--Pilih TA--</option>
                            @foreach ($tahunajarans as $ta)
                                <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Pelajaran</label>
                        <select wire:model="pelajaran_id"
                            class="form-select @error('pelajaran_id') is-invalid @enderror" required>
                            <option value="">--Pilih Pelajaran--</option>
                            @foreach ($pelajarans as $p)
                                <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Keterangan</label>
                        <input wire:model="keterangan" class="form-control" placeholder="Keterangan">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning w-100">UPDATE</button>
                        <button type="button" wire:click="cancelEdit"
                            class="btn btn-sm btn-link text-muted w-100">Batal</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 align-items-end mb-3">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Tahun Ajaran</label>
                        <select wire:model="tahun_ajaran_id"
                            class="form-select @error('tahun_ajaran_id') is-invalid @enderror" required>
                            <option value="">--Pilih--</option>
                            @foreach ($tahunajarans as $ta)
                                <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Pelajaran</label>
                        <select wire:model="pelajaran_id"
                            class="form-select @error('pelajaran_id') is-invalid @enderror" required>
                            <option value="">--Pilih--</option>
                            @foreach ($pelajarans as $p)
                                <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Kelas (Otomatis)</label>
                        <select wire:model="kelas_id" class="form-select">
                            <option value="">--Pilih Kelas--</option>
                            @foreach ($rombels as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Keterangan Pembelajaran</label>
                        <input wire:model="keterangan" class="form-control" placeholder="Keterangan">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i>
                            TAMBAH</button>
                    </div>
                    <div class="col-12 mt-1">
                        <small class="text-muted">Pilih <strong>Kelas</strong> untuk menambahkan Anggota otomatis dari
                            Rombel.</small>
                    </div>
                </form>
            @endif

            <div class="table-responsive mt-3">
                <table class="table-bordered table-hover table">
                    <thead class="table-light">
                        <tr>
                            <th>Keterangan</th>
                            <th>Tahun Ajaran</th>
                            <th>Pelajaran</th>
                            <th class="text-center">Member</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pembelajarans as $pb)
                            <tr wire:key="row-{{ $pb->id }}">
                                <td class="fw-bold">{{ $pb->keterangan }}</td>
                                <td>{{ $pb->tahunAjaran->nama }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $pb->pelajaran->nama }}</span>
                                </td>
                                <td class="text-center"><span
                                        class="badge bg-info text-white">{{ $pb->anggota->count() }}</span></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="{{ route('pembelajaran.jurnal.nilai.index', $pb->id) }}" wire:navigate
                                            class="btn btn-sm btn-outline-danger" title="Nilai"><i
                                                class="bi bi-star"></i></a>
                                        <a href="{{ route('pembelajaran.jurnal.index', $pb->id) }}" wire:navigate
                                            class="btn btn-sm btn-outline-warning" title="Jurnal"><i
                                                class="bi bi-book"></i></a>
                                        <a href="{{ route('pembelajaran.presensi.create', $pb->id) }}?tanggal={{ date('Y-m-d') }}"
                                            wire:navigate class="btn btn-sm btn-outline-primary" title="Presensi"><i
                                                class="bi bi-calendar-check"></i></a>
                                        <a href="{{ route('pembelajaran.anggota.index', $pb->id) }}" wire:navigate
                                            class="btn btn-sm btn-outline-success" title="Anggota"><i
                                                class="bi bi-people"></i></a>
                                        <button wire:click="edit('{{ $pb->id }}')"
                                            class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></button>
                                        <button wire:click="delete('{{ $pb->id }}')"
                                            wire:confirm="Hapus data ini beserta seluruh jurnal dan nilainya?"
                                            class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted py-4 text-center">Belum ada data pembelajaran</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $pembelajarans->links() }}
            </div>
        </div>
    </div>
</div>
