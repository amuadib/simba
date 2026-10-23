@section('title', 'Data Pembelajaran ' . session('tahun_ajaran_nama'))
<?php

use App\Models\Pembelajaran;
use App\Models\Pelajaran;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Jadwal;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $tahun_ajaran_id = '';
    public $pelajaran_id = '';
    public $kelas_id = '';
    public $keterangan = '';

    public $editingId = null;
    public $action = '';

    public $jadwal_pembelajaran_id = null;
    public $jadwal_user_id = '';
    public $jadwal_hari = '';
    public $jadwal_jam_mulai = '';
    public $jadwal_jam_selesai = '';

    public $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->tahun_ajaran_id = session('tahun_ajaran_id');
    }
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

        $this->reset(['pelajaran_id', 'kelas_id', 'keterangan']);
        $this->dispatch('toast', message: 'Pembelajaran berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $p = Pembelajaran::findOrFail($id);
        $this->editingId = $id;
        $this->action = 'edit';
        $this->pelajaran_id = $p->pelajaran_id;
        $this->keterangan = $p->keterangan;
    }

    public function update()
    {
        $this->validate([
            'pelajaran_id' => 'required',
            'keterangan' => 'nullable',
        ]);

        $p = Pembelajaran::findOrFail($this->editingId);
        $p->update([
            'pelajaran_id' => $this->pelajaran_id,
            'keterangan' => $this->keterangan,
        ]);

        $this->reset(['pelajaran_id', 'kelas_id', 'keterangan', 'editingId', 'action']);
        $this->dispatch('toast', message: 'Pembelajaran berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['pelajaran_id', 'kelas_id', 'keterangan', 'editingId', 'action']);
    }
    public function delete($id)
    {
        Pembelajaran::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Pembelajaran berhasil dihapus', type: 'success');
    }

    public function openJadwalModal($id)
    {
        $this->jadwal_pembelajaran_id = $id;
        $this->reset(['jadwal_user_id', 'jadwal_hari', 'jadwal_jam_mulai', 'jadwal_jam_selesai']);
    }

    public function storeJadwal()
    {
        $this->validate([
            'jadwal_pembelajaran_id' => 'required',
            'jadwal_user_id' => 'required',
            'jadwal_hari' => 'required|integer',
            'jadwal_jam_mulai' => 'required',
            'jadwal_jam_selesai' => 'required',
        ]);

        Jadwal::create([
            'pembelajaran_id' => $this->jadwal_pembelajaran_id,
            'user_id' => $this->jadwal_user_id,
            'hari' => $this->jadwal_hari,
            'jam_mulai' => $this->jadwal_jam_mulai,
            'jam_selesai' => $this->jadwal_jam_selesai,
        ]);

        $this->reset(['jadwal_user_id', 'jadwal_hari', 'jadwal_jam_mulai', 'jadwal_jam_selesai']);
        $this->dispatch('toast', message: 'Jadwal berhasil ditambahkan', type: 'success');
    }

    public function deleteJadwal($id)
    {
        Jadwal::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Jadwal berhasil dihapus', type: 'success');
    }
    public function duplicateJadwal($id)
    {
        $jadwal = Jadwal::findOrFail($id);
        $this->jadwal_pembelajaran_id = $jadwal->pembelajaran_id;
        $this->jadwal_user_id = $jadwal->user_id;
        $this->jadwal_hari = $jadwal->hari;
        $this->jadwal_jam_mulai = $jadwal->jam_mulai;
        $this->jadwal_jam_selesai = $jadwal->jam_selesai;
    }

    public function with()
    {
        return [
            'pembelajarans' => Pembelajaran::with(['tahunAjaran', 'pelajaran', 'anggota', 'jadwal.user'])
                ->where('tahun_ajaran_id', session('tahun_ajaran_id'))
                ->orderBy('keterangan', 'asc')->paginate(15),
            'pelajarans' => Pelajaran::orderBy('nama', 'desc')->get(),
            'rombels' => Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))
            ->orderBy('nama', 'desc')
            ->get(),
            'users' => User::orderBy('name')->get(),
            'hariOptions' => [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Ahad',
            ],
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-0">Data Pembelajaran</h4>
            <small class="text-muted">Kelola mata pelajaran dan pengampu</small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('pembelajaran.presensi.index') }}" wire:navigate class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-clock-history"></i>
                <span>Rekap Presensi</span>
            </a>
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($action == 'edit')
                <form wire:submit="update" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tahun Ajaran</label>
                        <span class="form-control small fw-bold">{{ session('tahun_ajaran_nama') }}</span>
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
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-warning w-100"><i class="bi bi-save me-1"></i> SIMPAN</button>
                        <button type="button" wire:click="cancelEdit"
                            class="btn btn-secondary w-100">BATAL</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tahun Ajaran</label>
                        <span class="form-control small fw-bold">{{ session('tahun_ajaran_nama') }}</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Pelajaran</label>
                        <select wire:model="pelajaran_id"
                            class="form-select @error('pelajaran_id') is-invalid @enderror" required>
                            <option value="">--Pilih--</option>
                            @foreach ($pelajarans as $p)
                                <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
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

            @php
                $hariColors = [
                    1 => 'bg-primary',
                    2 => 'bg-dark',
                    3 => 'bg-info text-dark',
                    4 => 'bg-warning text-dark',
                    5 => 'bg-success',
                    6 => 'bg-secondary',
                    7 => 'bg-danger',
                ];
            @endphp

            <div class="table-responsive mt-3">
                <table class="table-bordered table-hover table">
                    <thead class="table-light">
                        <tr>
                            <th>Pelajaran</th>
                            <th class="text-center">Anggota</th>
                            <th>Jadwal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pembelajarans as $pb)
                            <tr wire:key="row-{{ $pb->id }}">
                                <td class="fw-bold">{{ $pb->keterangan }}</td>
                                <td class="text-center">
                                    @if($pb->anggota->count() > 0)
                                        <span class="badge bg-info text-white">{{ $pb->anggota->count() }}</span>
                                    @else
                                        <span class="badge bg-danger">Belum ada anggota</span>
                                    @endif
                                </td>
                                <td>
                                    @if($pb->jadwal->count() > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach ($pb->jadwal->sortBy('hari') as $j)
                                                <div class="d-flex align-items-center gap-2 bg-light border rounded px-2 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                    <span class="badge {{ $hariColors[$j->hari] ?? 'bg-primary' }} rounded-pill text-uppercase" style="min-width: 60px;">{{ $j->hari_text }}</span>
                                                    <span class="text-secondary fw-bold">
                                                        <i class="bi bi-clock me-1"></i>{{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-calendar-x me-1"></i>Belum ada jadwal
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button wire:click="openJadwalModal('{{ $pb->id }}')"
                                            class="btn btn-sm btn-outline-primary" title="Jadwal"><i class="bi bi-calendar-week"></i></button>
                                        <a href="{{ route('pembelajaran.jurnal.nilai.index', $pb->id) }}" wire:navigate
                                            class="btn btn-sm btn-outline-danger" title="Nilai"><i
                                                class="bi bi-star"></i></a>
                                        <a href="{{ route('pembelajaran.jurnal.index', $pb->id) }}" wire:navigate
                                            class="btn btn-sm btn-outline-warning" title="Jurnal"><i
                                                class="bi bi-book"></i></a>
                                        <a href="{{ route('pembelajaran.presensi.create', $pb->id) }}?tanggal={{ date('Y-m-d') }}"
                                            wire:navigate class="btn btn-sm btn-outline-primary" title="Input Presensi Hari Ini"><i
                                                class="bi bi-calendar-check"></i></a>
                                        <a href="{{ route('pembelajaran.presensi.index', ['pembelajaran_id' => $pb->id]) }}"
                                            wire:navigate class="btn btn-sm btn-outline-info" title="Rekap Presensi"><i
                                                class="bi bi-clock-history"></i></a>
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
                                <td colspan="6" class="text-muted py-4 text-center">Belum ada data pembelajaran</td>
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

    {{-- MODAL JADWAL --}}
    @if ($jadwal_pembelajaran_id)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="w-full max-w-3xl rounded-3xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="bg-gradient-to-r from-sky-600 to-cyan-600 text-white p-4 flex items-center justify-between">
                    <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm">
                        <i class="bi bi-calendar-week text-cyan-200"></i> Kelola Jadwal Pembelajaran
                    </h5>
                    <button type="button" class="text-white hover:text-slate-200" wire:click="$set('jadwal_pembelajaran_id', null)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-5">
                    @php
                        $pbData = App\Models\Pembelajaran::with('jadwal.user', 'pelajaran')->find($jadwal_pembelajaran_id);
                    @endphp
                    @if ($pbData)
                        <div class="p-3 rounded-2xl mb-4 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-900 text-xs text-sky-800 dark:text-cyan-300 flex items-center gap-2">
                            <i class="bi bi-info-circle text-base text-sky-600 dark:text-cyan-400"></i>
                            <div>
                                <strong>{{ $pbData->keterangan }}</strong> ({{ $pbData->pelajaran->nama ?? '' }})
                            </div>
                        </div>

                        <div class="table-responsive rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs mb-4">
                            <table class="table table-hover align-middle mb-0 text-xs">
                                <thead class="bg-slate-100 dark:bg-slate-800 font-display">
                                    <tr>
                                        <th class="py-2.5">Hari</th>
                                        <th class="py-2.5">Jam</th>
                                        <th class="py-2.5">Pengampu</th>
                                        <th class="text-center py-2.5" style="width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($pbData->jadwal->sortBy('hari') as $j)
                                        <tr wire:key="jadwal-{{ $j->id }}">
                                            <td>
                                                <span class="badge {{ $hariColors[$j->hari] ?? 'bg-primary' }} rounded-pill text-uppercase px-2.5 py-1 text-[11px]" style="min-width: 60px;">
                                                    {{ $j->hari_text }}
                                                </span>
                                            </td>
                                            <td class="font-mono font-medium">{{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }}</td>
                                            <td class="font-semibold">{{ $j->user->name ?? '-' }}</td>
                                            <td class="text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button wire:click="duplicateJadwal('{{ $j->id }}')" class="p-1 px-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800" title="Duplikat"><i class="bi bi-copy"></i></button>
                                                    <button wire:click="deleteJadwal('{{ $j->id }}')" wire:confirm="Hapus jadwal ini?" class="p-1 px-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 border border-rose-200 dark:border-rose-800" title="Hapus"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-slate-400 text-xs py-3 italic">Belum ada jadwal</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <form wire:submit="storeJadwal" class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs space-y-3">
                            <h6 class="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-cyan-400 font-display flex items-center gap-1.5 mb-2">
                                @if ($jadwal_hari == '')
                                    <i class="bi bi-plus-circle"></i> Tambah Jadwal Baru
                                @else
                                    <i class="bi bi-copy"></i> Duplikat Jadwal
                                @endif
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-3">
                                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Hari</label>
                                    <select wire:model="jadwal_hari" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                        <option value="">--Pilih Hari--</option>
                                        @foreach ($hariOptions as $k => $v)
                                            <option value="{{ $k }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Mulai</label>
                                    <input type="time" wire:model="jadwal_jam_mulai" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Selesai</label>
                                    <input type="time" wire:model="jadwal_jam_selesai" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Pengampu</label>
                                    <select wire:model="jadwal_user_id" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                        <option value="">--Pilih Guru--</option>
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <button type="submit" class="btn-logo-gradient w-100 py-1.5 rounded-xl text-xs font-bold shadow-xs flex items-center justify-center gap-1.5"><i class="bi bi-save"></i> SIMPAN</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="text-center py-4 text-slate-400 text-xs">Memuat data...</div>
                    @endif
                </div>
                <div class="p-3 border-t border-slate-200 dark:border-slate-800 flex justify-end bg-slate-50 dark:bg-slate-800/80">
                    <button type="button" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold" wire:click="$set('jadwal_pembelajaran_id', null)">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
