@section('title', 'Data Pegawai')

<?php

use App\Models\Pegawai;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PegawaiImport;
use App\Exports\PegawaiExport;

new class extends \Livewire\Volt\Component {
    use WithPagination, WithFileUploads;

    // Filter & Search
    public $q = '';
    public $status = '';
    public $jenis_kelamin = '';
    public $lembaga_id = '';

    // Action State
    public $action = ''; // '', 'create', 'edit', 'show'
    public $editingId = null;
    public $showId = null;

    // Form Fields
    public $niy, $nuptk, $nama, $gelar_depan, $gelar_belakang, $form_jenis_kelamin = 'l';
    public $nik, $tempat_lahir, $tanggal_lahir, $tmt, $no_hp, $alamat, $form_status = '1', $form_lembaga_id = 99;
    public $foto, $existing_foto;

    // Checkbox Selection
    public $checkedPegawai = [];
    public $selectAll = false;
    public $randomConfirmString = '';
    public $confirmText = '';

    // Bulk Actions
    public $bulkStatus = '';
    public $bulkLembagaId = '';

    // Import
    public $importFile;

    public $paginationTheme = 'bootstrap';

    public function updated($property)
    {
        if (in_array($property, ['q', 'status', 'jenis_kelamin', 'lembaga_id'])) {
            $this->resetPage();
            $this->checkedPegawai = [];
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->checkedPegawai = Pegawai::query()
                ->when($this->status, fn($q, $s) => $q->where('status', $s))
                ->when($this->jenis_kelamin, fn($q, $jk) => $q->where('jenis_kelamin', strtolower($jk)))
                ->when($this->lembaga_id, fn($q, $lid) => $q->where('lembaga_id', $lid))
                ->when($this->q, fn($q, $search) => $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('niy', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('no_hp', 'like', "%{$search}%");
                }))
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        } else {
            $this->checkedPegawai = [];
        }
    }

    public function updatedCheckedPegawai()
    {
        $this->selectAll = false;
    }

    public function prepareBulkDelete()
    {
        $this->randomConfirmString = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $this->confirmText = '';
        $this->dispatch('show-modal-delete');
    }

    public function bulkDelete()
    {
        if (strtoupper($this->confirmText) !== $this->randomConfirmString) {
            $this->dispatch('toast', message: 'Karakter konfirmasi tidak cocok', type: 'error');
            return;
        }

        $pegawais = Pegawai::whereIn('id', $this->checkedPegawai)->get();
        foreach ($pegawais as $p) {
            if ($p->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($p->foto)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($p->foto);
            }
            $p->delete();
        }

        $this->checkedPegawai = [];
        $this->selectAll = false;
        $this->dispatch('close-modal', id: 'modalBulkDelete');
        $this->dispatch('toast', message: 'Data pegawai berhasil dihapus', type: 'success');
    }

    public function bulkExport()
    {
        return Excel::download(new PegawaiExport($this->checkedPegawai, 'data'), 'SIMBA-ekspor-pegawai-' . date('YmdHis') . '.xlsx');
    }

    public function exportAll()
    {
        return Excel::download(new PegawaiExport([], 'data'), 'SIMBA-ekspor-semua-pegawai-' . date('YmdHis') . '.xlsx');
    }

    // --- CRUD ACTIONS ---

    public function create()
    {
        $this->resetForm();
        $this->action = 'create';
        $this->dispatch('show-modal-pegawai');
    }

    public function store()
    {
        $this->validate([
            'nama' => 'required|string|max:255',
            'form_jenis_kelamin' => 'required|in:l,p,L,P',
            'niy' => 'nullable|string|max:100',
            'nuptk' => 'nullable|string|max:100',
            'tmt' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'nik' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:50',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'foto' => 'nullable|image|max:2048',
            'form_lembaga_id' => 'required',
            'form_status' => 'required',
        ]);

        $niy = trim((string) $this->niy);
        if (empty($niy)) {
            $niy = Pegawai::generateNiy($this->tmt, $this->form_jenis_kelamin);
        }

        $fotoPath = $this->foto ? $this->foto->store('pegawai', 'public') : null;

        Pegawai::create([
            'nama' => $this->nama,
            'niy' => $niy,
            'nuptk' => $this->nuptk ?: null,
            'tmt' => $this->tmt ?: null,
            'jenis_kelamin' => strtolower($this->form_jenis_kelamin),
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir ?: null,
            'nik' => $this->nik,
            'alamat' => $this->alamat,
            'no_hp' => $this->no_hp,
            'gelar_depan' => $this->gelar_depan,
            'gelar_belakang' => $this->gelar_belakang,
            'foto' => $fotoPath,
            'lembaga_id' => $this->form_lembaga_id ?: 99,
            'status' => (string) $this->form_status,
        ]);

        $this->resetForm();
        $this->dispatch('close-modal', id: 'modalPegawaiForm');
        $this->dispatch('toast', message: 'Pegawai berhasil ditambahkan', type: 'success');
    }

    public function show($id)
    {
        $this->editingId = null;
        $this->showId = $id;
        $this->action = 'show';
        $this->dispatch('show-modal-pegawai');
    }

    public function edit($id)
    {
        $this->showId = null;
        $this->editingId = $id;
        $this->action = 'edit';

        $pegawai = Pegawai::findOrFail($id);
        $this->nama = $pegawai->nama;
        $this->niy = $pegawai->niy;
        $this->nuptk = $pegawai->nuptk;
        $this->tmt = $pegawai->tmt ? $pegawai->tmt->format('Y-m-d') : null;
        $this->form_jenis_kelamin = $pegawai->jenis_kelamin;
        $this->tempat_lahir = $pegawai->tempat_lahir;
        $this->tanggal_lahir = $pegawai->tanggal_lahir ? $pegawai->tanggal_lahir->format('Y-m-d') : null;
        $this->nik = $pegawai->nik;
        $this->alamat = $pegawai->alamat;
        $this->no_hp = $pegawai->no_hp;
        $this->gelar_depan = $pegawai->gelar_depan;
        $this->gelar_belakang = $pegawai->gelar_belakang;
        $this->existing_foto = $pegawai->foto;
        $this->foto = null;
        $this->form_lembaga_id = $pegawai->lembaga_id ?: 99;
        $this->form_status = (string) $pegawai->status;

        $this->dispatch('show-modal-pegawai');
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required|string|max:255',
            'form_jenis_kelamin' => 'required|in:l,p,L,P',
            'niy' => 'nullable|string|max:100',
            'nuptk' => 'nullable|string|max:100',
            'tmt' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'nik' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:50',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'foto' => 'nullable|image|max:2048',
            'form_lembaga_id' => 'required',
            'form_status' => 'required',
        ]);

        $pegawai = Pegawai::findOrFail($this->editingId);

        $niy = trim((string) $this->niy);
        if (empty($niy)) {
            $niy = Pegawai::generateNiy($this->tmt, $this->form_jenis_kelamin);
        }

        $fotoPath = $pegawai->foto;
        if ($this->foto) {
            if ($pegawai->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pegawai->foto)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($pegawai->foto);
            }
            $fotoPath = $this->foto->store('pegawai', 'public');
        }

        $pegawai->update([
            'nama' => $this->nama,
            'niy' => $niy,
            'nuptk' => $this->nuptk ?: null,
            'tmt' => $this->tmt ?: null,
            'jenis_kelamin' => strtolower($this->form_jenis_kelamin),
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir ?: null,
            'nik' => $this->nik,
            'alamat' => $this->alamat,
            'no_hp' => $this->no_hp,
            'gelar_depan' => $this->gelar_depan,
            'gelar_belakang' => $this->gelar_belakang,
            'foto' => $fotoPath,
            'lembaga_id' => $this->form_lembaga_id ?: 99,
            'status' => (string) $this->form_status,
        ]);

        $this->resetForm();
        $this->dispatch('close-modal', id: 'modalPegawaiForm');
        $this->dispatch('toast', message: 'Pegawai berhasil diperbarui', type: 'success');
    }

    public function delete($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        if ($pegawai->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pegawai->foto)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($pegawai->foto);
        }
        $pegawai->delete();
        $this->dispatch('toast', message: 'Pegawai berhasil dihapus', type: 'success');
    }

    public function resetForm()
    {
        $this->reset([
            'nama', 'niy', 'nuptk', 'tmt', 'tempat_lahir', 'tanggal_lahir',
            'nik', 'alamat', 'no_hp', 'gelar_depan', 'gelar_belakang',
            'foto', 'existing_foto', 'action', 'editingId', 'showId'
        ]);
        $this->form_jenis_kelamin = 'l';
        $this->form_lembaga_id = 99;
        $this->form_status = '1';
    }

    public function bulkUpdate()
    {
        $this->validate([
            'bulkStatus' => 'nullable',
            'bulkLembagaId' => 'nullable',
        ]);

        if (empty($this->checkedPegawai)) {
            $this->dispatch('toast', message: 'Tidak ada pegawai yang dipilih', type: 'warning');
            return;
        }

        if (empty($this->bulkStatus) && empty($this->bulkLembagaId)) {
            $this->dispatch('toast', message: 'Pilih minimal satu data yang ingin diperbarui secara massal', type: 'warning');
            return;
        }

        $updateData = [];
        if (!empty($this->bulkStatus)) {
            $updateData['status'] = (string) $this->bulkStatus;
        }
        if (!empty($this->bulkLembagaId)) {
            $updateData['lembaga_id'] = $this->bulkLembagaId;
        }

        Pegawai::whereIn('id', $this->checkedPegawai)->update($updateData);

        $this->reset(['bulkStatus', 'bulkLembagaId', 'checkedPegawai', 'selectAll']);
        $this->dispatch('close-modal', id: 'modalBulkUpdate');
        $this->dispatch('toast', message: 'Data pegawai berhasil diperbarui secara massal', type: 'success');
    }

    public function import()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new PegawaiImport();
        Excel::import($import, $this->importFile->getRealPath());
        $results = $import->getResults();
        $errorsCount = count($results['errors']);
        $message = "Impor data pegawai selesai. Sukses: {$results['success']}" . ($errorsCount > 0 ? ", Gagal: {$errorsCount}" : "");
        $this->reset(['importFile']);
        $this->dispatch('toast', message: $message, type: $errorsCount > 0 ? 'warning' : 'success');
    }

    public function exportTemplate()
    {
        return Excel::download(new PegawaiExport([], 'template-import'), 'SIMBA-template-import-pegawai-' . date('YmdHis') . '.xlsx');
    }

    public function with()
    {
        $pegawai = Pegawai::query()
            ->orderBy('niy')
            ->when($this->status, fn($q, $s) => $q->where('status', $s))
            ->when($this->jenis_kelamin, fn($q, $jk) => $q->where('jenis_kelamin', strtolower($jk)))
            ->when($this->lembaga_id, fn($q, $lid) => $q->where('lembaga_id', $lid))
            ->when($this->q, fn($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('niy', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            }))
            ->paginate(25);

        $counts = [
            'total' => Pegawai::count(),
            'aktif' => Pegawai::where('status', '1')->count(),
            'laki' => Pegawai::where('jenis_kelamin', 'l')->count(),
            'perempuan' => Pegawai::where('jenis_kelamin', 'p')->count(),
        ];

        return [
            'pegawaiList' => $pegawai,
            'counts' => $counts,
            'daftarLembaga' => daftar_lembaga(),
            'statusOptions' => config('local.status_pegawai', [
                1 => 'Aktif',
                2 => 'Cuti',
                3 => 'Non-Aktif',
                4 => 'Mutasi',
                5 => 'Pensiun',
                6 => 'Keluar',
            ]),
        ];
    }
};

?>

<div>
    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Pegawai</h4>
            <small class="text-muted">Kelola data tenaga pendidik dan kependidikan (Pegawai)</small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold">Total Pegawai</div>
                            <div class="fs-4 fw-bold text-primary">{{ $counts['total'] }}</div>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold">Pegawai Aktif</div>
                            <div class="fs-4 fw-bold text-success">{{ $counts['aktif'] }}</div>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-2 rounded-3">
                            <i class="bi bi-person-check-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold">Laki-laki</div>
                            <div class="fs-4 fw-bold text-info">{{ $counts['laki'] }}</div>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-2 rounded-3">
                            <i class="bi bi-gender-male fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-danger h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold">Perempuan</div>
                            <div class="fs-4 fw-bold text-danger">{{ $counts['perempuan'] }}</div>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-2 rounded-3">
                            <i class="bi bi-gender-female fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CARD --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-body">

            {{-- TABS AREA --}}
            <div x-data="{ activeTab: 'filter' }">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'filter' }" @click="activeTab = 'filter'" type="button" role="tab">
                            <i class="bi bi-funnel me-1"></i> Filter & Pencarian
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'import' }" @click="activeTab = 'import'" type="button" role="tab">
                            <i class="bi bi-upload me-1"></i> Import Excel
                        </button>
                    </li>

                    {{-- TOMBOL AKSI SEJAJAR TAB --}}
                    <li class="nav-item ms-auto d-flex align-items-center gap-2 pe-1 pb-1 flex-wrap">
                        @if (count($checkedPegawai) > 0)
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulkUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Update Status ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="prepareBulkDelete">
                                <i class="bi bi-trash me-1"></i> Hapus ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="bulkExport">
                                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ({{ count($checkedPegawai) }})
                            </button>
                        @endif

                        <button type="button" wire:click="exportAll" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-download me-1"></i> Ekspor Semua
                        </button>

                        <button wire:click="create" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Pegawai
                        </button>
                    </li>
                </ul>

                <div class="tab-content mb-4">
                    {{-- TAB FILTER --}}
                    <div x-show="activeTab === 'filter'">
                        <div class="rounded p-3" style="background-color: var(--table-bg); border: 1px solid var(--table-border);">
                            <form wire:submit.prevent class="row g-2">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0" style="background-color: var(--bg-card); border-color: var(--table-border);">
                                            <i class="bi bi-search text-muted"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="q" class="form-control border-start-0" placeholder="Cari nama, NIY, NIK, No HP...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select wire:model.live="lembaga_id" class="form-select">
                                        <option value="">-- Semua Lembaga --</option>
                                        @foreach ($daftarLembaga as $lid => $lname)
                                            <option value="{{ $lid }}">{{ $lname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="status" class="form-select">
                                        <option value="">-- Semua Status --</option>
                                        @foreach ($statusOptions as $k => $v)
                                            <option value="{{ $k }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="jenis_kelamin" class="form-select">
                                        <option value="">-- Semua JK --</option>
                                        <option value="l">Laki-laki</option>
                                        <option value="p">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" wire:click="resetPage" class="btn btn-success w-100" title="Reset Filter">
                                        <i class="bi bi-funnel"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- TAB IMPORT --}}
                    <div x-show="activeTab === 'import'" style="display: none;">
                        <div class="rounded border p-3" style="background-color: var(--table-bg); border-color: var(--table-border);">
                            <form wire:submit="import" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <input type="file" wire:model="importFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                                    @error('importFile')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-warning w-100" wire:loading.attr="disabled">
                                        <i class="bi bi-upload me-1"></i> IMPORT EXCEL
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="exportTemplate" class="btn btn-outline-primary w-100" wire:loading.attr="disabled">
                                        <i class="bi bi-download me-1"></i> UNDUH TEMPLATE
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="table-responsive">
                <table class="table table-hover border align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;" class="text-center">
                                <div class="form-check d-flex justify-content-center m-0 p-0">
                                    <input class="form-check-input m-0" type="checkbox" wire:model.live="selectAll">
                                </div>
                            </th>
                            <th style="width: 50px;">No</th>
                            <th>NIY</th>
                            <th>Nama Pegawai</th>
                            <th class="text-center">JK</th>
                            <th>Lembaga</th>
                            <th>TMT</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pegawaiList as $p)
                            <tr wire:key="row-{{ $p->id }}" id="tr-{{ $p->id }}">
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center m-0 p-0">
                                        <input class="form-check-input m-0" type="checkbox" wire:model.live="checkedPegawai" value="{{ $p->id }}">
                                    </div>
                                </td>
                                <td>{{ $loop->iteration + ($pegawaiList->currentPage() - 1) * $pegawaiList->perPage() }}</td>
                                <td>
                                    <div>{{ $p->niy ?: '-' }}</div>
                                    @if ($p->nuptk)
                                        <div class="small text-muted" style="font-size: 0.72rem;"><i class="bi bi-award me-1"></i>{{ $p->nuptk }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($p->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($p->foto))
                                            <img src="{{ asset('storage/' . $p->foto) }}" alt="Foto" class="rounded-circle object-fit-cover shadow-sm flex-shrink-0" style="width: 36px; height: 36px;">
                                        @else
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-secondary bg-light border flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div>{!! setNamaPegawai($p->nama, $p->gelar_depan, $p->gelar_belakang) !!}</div>
                                            @if ($p->nik)
                                                <div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-card-text me-1"></i>NIK: {{ $p->nik }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if (strtolower($p->jenis_kelamin) === 'l')
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Laki-laki">L</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Perempuan">P</span>
                                    @endif
                                </td>
                                <td>
                                    {{ nama_lembaga($p->lembaga_id,true) }}
                                </td>
                                <td>
                                    @if ($p->tmt)
                                        <div>{{ $p->tmt->format('d M Y') }}</div>
                                        @if ($p->status == 1 || $p->status == 2)
                                            <small class="text-muted">{{ $p->tmt->diffForHumans(null, true) }} kerja</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->no_hp)
                                        @php
                                            $cleanHp = preg_replace('/[^0-9]/', '', $p->no_hp);
                                            if (str_starts_with($cleanHp, '0')) {
                                                $waNumber = '62' . substr($cleanHp, 1);
                                            } else {
                                                $waNumber = $cleanHp;
                                            }
                                        @endphp
                                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" class="text-decoration-none text-success small">
                                            <i class="bi bi-whatsapp me-1"></i>{{ $p->no_hp }}
                                        </a>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badges = [
                                            '1' => 'bg-success',
                                            '2' => 'bg-warning text-dark',
                                            '3' => 'bg-secondary',
                                            '4' => 'bg-info text-dark',
                                            '5' => 'bg-dark',
                                            '6' => 'bg-danger',
                                        ];
                                    @endphp
                                    <span class="badge {{ $badges[(string) $p->status] ?? 'bg-secondary' }}">
                                        {{ $statusOptions[(string) $p->status] ?? $p->status }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="show('{{ $p->id }}')" class="btn btn-outline-info" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button wire:click="edit('{{ $p->id }}')" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button wire:click="delete('{{ $p->id }}')" wire:confirm="Apakah Anda yakin ingin menghapus data pegawai ini?" class="btn btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-5 text-center">
                                    <i class="bi bi-person-x fs-2 text-muted d-block mb-2"></i>
                                    Tidak ditemukan data pegawai
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $pegawaiList->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL PEGAWAI FORM & DETAIL --}}
    <div wire:ignore.self class="modal fade" id="modalPegawaiForm" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if ($action == 'edit')
                            <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Data Pegawai
                        @elseif ($action == 'create')
                            <i class="bi bi-plus-circle me-2 text-primary"></i> Tambah Data Pegawai
                        @else
                            <i class="bi bi-person-vcard me-2 text-info"></i> Detail Data Pegawai
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="resetForm"></button>
                </div>
                <div class="modal-body">
                    @if ($action == 'edit' || $action == 'create')
                        <form wire:submit="{{ $action == 'edit' ? 'update' : 'store' }}" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Gelar Depan</label>
                                <input wire:model="gelar_depan" class="form-control" placeholder="Contoh: Dr., Drs., H.">
                                @error('gelar_depan') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input wire:model="nama" class="form-control" placeholder="Nama Lengkap tanpa gelar" required>
                                @error('nama') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Gelar Belakang</label>
                                <input wire:model="gelar_belakang" class="form-control" placeholder="Contoh: M.Pd., S.Pd.">
                                @error('gelar_belakang') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jenis Kelamin <span class="text-danger">*</span></label>
                                <div class="d-flex gap-4 pt-2">
                                    <div class="form-check">
                                        <input wire:model="form_jenis_kelamin" class="form-check-input" type="radio" value="l" id="jkPegawaiL">
                                        <label class="form-check-label" for="jkPegawaiL">Laki-laki (L)</label>
                                    </div>
                                    <div class="form-check">
                                        <input wire:model="form_jenis_kelamin" class="form-check-input" type="radio" value="p" id="jkPegawaiP">
                                        <label class="form-check-label" for="jkPegawaiP">Perempuan (P)</label>
                                    </div>
                                </div>
                                @error('form_jenis_kelamin') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">NIY (Nomor Induk Yayasan)</label>
                                <input wire:model="niy" class="form-control" placeholder="Otomatis jika kosong">
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Dikosongkan jika ingin dibuat otomatis (Tahun + Bulan TMT + 001/002 + No Urut)</div>
                                @error('niy') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">NUPTK</label>
                                <input wire:model="nuptk" class="form-control" placeholder="Nomor Unik Pendidik & Tenaga Kependidikan">
                                @error('nuptk') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">NIK (KTP)</label>
                                <input wire:model="nik" class="form-control" placeholder="Nomor Induk Kependudukan">
                                @error('nik') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tempat Lahir</label>
                                <input wire:model="tempat_lahir" class="form-control" placeholder="Tempat Lahir">
                                @error('tempat_lahir') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tanggal Lahir</label>
                                <input type="date" wire:model="tanggal_lahir" class="form-control">
                                @error('tanggal_lahir') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">TMT (Mulai Bertugas)</label>
                                <input type="date" wire:model="tmt" class="form-control">
                                @error('tmt') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Lembaga <span class="text-danger">*</span></label>
                                <select wire:model="form_lembaga_id" class="form-select" required>
                                    <option value="">-- Pilih Lembaga --</option>
                                    @foreach ($daftarLembaga as $lid => $lname)
                                        <option value="{{ $lid }}">{{ $lname }}</option>
                                    @endforeach
                                </select>
                                @error('form_lembaga_id') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Pegawai <span class="text-danger">*</span></label>
                                <select wire:model="form_status" class="form-select" required>
                                    <option value="">-- Pilih Status --</option>
                                    @foreach ($statusOptions as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('form_status') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor HP / WhatsApp</label>
                                <input wire:model="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
                                @error('no_hp') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Foto Pegawai</label>
                                <input type="file" wire:model="foto" class="form-control" accept="image/*">
                                <div wire:loading wire:target="foto" class="mt-1 small text-primary">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Mengunggah foto...
                                </div>
                                @error('foto') <span class="text-danger small">{{ $message }}</span> @enderror
                                <div class="mt-2">
                                    @if ($foto && !is_string($foto))
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $foto->temporaryUrl() }}" class="img-thumbnail rounded" style="max-height: 80px;">
                                            <span class="small text-muted">Preview foto baru</span>
                                        </div>
                                    @elseif ($existing_foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($existing_foto))
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ asset('storage/' . $existing_foto) }}" class="img-thumbnail rounded" style="max-height: 80px;">
                                            <span class="small text-muted">Foto saat ini</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Alamat Lengkap</label>
                                <textarea wire:model="alamat" class="form-control" rows="3" placeholder="Alamat domisili lengkap"></textarea>
                                @error('alamat') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" wire:click="resetForm">Batal</button>
                                <button type="submit" class="btn btn-{{ $action == 'edit' ? 'warning' : 'primary' }}">
                                    <i class="bi bi-save me-1"></i> {{ $action == 'edit' ? 'Perbarui Pegawai' : 'Simpan Pegawai' }}
                                </button>
                            </div>
                        </form>
                    @elseif ($action == 'show')
                        @php
                            $pData = App\Models\Pegawai::find($showId);
                        @endphp
                        @if ($pData)
                            <div class="p-2">
                                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                                    @if ($pData->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pData->foto))
                                        <img src="{{ asset('storage/' . $pData->foto) }}" alt="Foto" class="rounded-circle object-fit-cover shadow-sm border border-2 border-primary" style="width: 64px; height: 64px;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bg-danger' : 'bg-primary' }}" style="width: 64px; height: 64px; font-size: 1.75rem;">
                                            <i class="bi {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bi-gender-female' : 'bi-gender-male' }}"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <h4 class="mb-1">{!! setNamaPegawai($pData->nama, $pData->gelar_depan, $pData->gelar_belakang) !!}</h4>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge bg-secondary">{{ $pData->niy ? 'NIY: ' . $pData->niy : 'Tanpa NIY' }}</span>
                                            @if ($pData->nuptk)
                                                <span class="badge bg-info text-dark">NUPTK: {{ $pData->nuptk }}</span>
                                            @endif
                                            <span class="badge {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bg-danger' : 'bg-primary' }}">{{ $pData->jenis_kelamin_label }}</span>
                                            <span class="badge bg-dark bg-opacity-75">{{ nama_lembaga($pData->lembaga_id) }}</span>
                                            <span class="badge bg-success">{{ $pData->status_label }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-building me-1"></i> Lembaga / Unit</div>
                                            <div class="fw-semibold">{{ nama_lembaga($pData->lembaga_id) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-award me-1"></i> NUPTK</div>
                                            <div class="fw-semibold">{{ $pData->nuptk ?: '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-card-text me-1"></i> NIK (KTP)</div>
                                            <div class="fw-semibold">{{ $pData->nik ?: '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-calendar-event me-1"></i> Tempat, Tanggal Lahir</div>
                                            <div class="fw-semibold">
                                                {{ $pData->tempat_lahir ?: '-' }}, {{ $pData->tanggal_lahir ? $pData->tanggal_lahir->format('d F Y') : '-' }}
                                                @if ($pData->tanggal_lahir)
                                                    <span class="text-muted small">({{ $pData->tanggal_lahir->age }} tahun)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-clock-history me-1"></i> TMT (Mulai Bertugas)</div>
                                            <div class="fw-semibold">
                                                {{ $pData->tmt ? $pData->tmt->format('d F Y') : '-' }}
                                                @if ($pData->tmt)
                                                    <span class="text-muted small">({{ $pData->tmt->diffForHumans(null, true) }})</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-telephone me-1"></i> Kontak / WhatsApp</div>
                                            <div class="fw-semibold">
                                                @if ($pData->no_hp)
                                                    @php
                                                        $cleanHp = preg_replace('/[^0-9]/', '', $pData->no_hp);
                                                        $waNum = str_starts_with($cleanHp, '0') ? '62' . substr($cleanHp, 1) : $cleanHp;
                                                    @endphp
                                                    <a href="https://wa.me/{{ $waNum }}" target="_blank" class="text-success text-decoration-none">
                                                        <i class="bi bi-whatsapp me-1"></i>{{ $pData->no_hp }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border" style="background-color: var(--table-bg);">
                                            <div class="text-muted small fw-bold mb-1"><i class="bi bi-geo-alt me-1"></i> Alamat Domisili</div>
                                            <div class="fw-semibold">{{ $pData->alamat ?: '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 rounded border small text-muted" style="background-color: var(--table-bg);">
                                            <div class="row">
                                                <div class="col-md-6">ID Pegawai (UUID): <code>{{ $pData->id }}</code></div>
                                                <div class="col-md-3">Dibuat: {{ $pData->created_at ? $pData->created_at->format('d/m/Y H:i') : '-' }}</div>
                                                <div class="col-md-3">Diperbarui: {{ $pData->updated_at ? $pData->updated_at->format('d/m/Y H:i') : '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                    <button type="button" class="btn btn-warning btn-sm" wire:click="edit('{{ $pData->id }}')">
                                        <i class="bi bi-pencil me-1"></i> Edit Pegawai
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" wire:click="resetForm">Tutup</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL BULK UPDATE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkUpdate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Data Massal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Perubahan akan diterapkan pada <strong>{{ count($checkedPegawai) }}</strong> pegawai yang dipilih.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Lembaga Baru</label>
                        <select wire:model="bulkLembagaId" class="form-select">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($daftarLembaga as $lid => $lname)
                                <option value="{{ $lid }}">{{ $lname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Status Baru</label>
                        <select wire:model="bulkStatus" class="form-select">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($statusOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="bulkUpdate" class="btn btn-primary w-100" wire:loading.attr="disabled">
                        <i class="bi bi-save me-1"></i> SIMPAN PERUBAHAN MASSAL
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL BULK DELETE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus Massal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus <strong>{{ count($checkedPegawai) }}</strong> pegawai yang dipilih?</p>
                    <p class="text-danger small"><i class="bi bi-info-circle me-1"></i>Tindakan ini permanen dan tidak dapat dibatalkan!</p>

                    <div class="mb-3">
                        <label class="form-label">Ketik karakter berikut untuk konfirmasi: <strong class="fs-5 ms-2 user-select-none">{{ $randomConfirmString }}</strong></label>
                        <input type="text" wire:model="confirmText" class="form-control" placeholder="Ketik karakter konfirmasi..." autocomplete="off">
                    </div>

                    <button wire:click="bulkDelete" class="btn btn-danger w-100" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-1"></i> HAPUS PEGAWAI TERPILIH
                    </button>
                </div>
            </div>
        </div>
    </div>

    @script
        <script>
            $wire.on('close-modal', ({ id }) => {
                const el = document.getElementById(id);
                if (el) {
                    bootstrap.Modal.getInstance(el)?.hide();
                }
            });

            $wire.on('show-modal-delete', () => {
                const el = document.getElementById('modalBulkDelete');
                if (el) {
                    new bootstrap.Modal(el).show();
                }
            });

            $wire.on('show-modal-pegawai', () => {
                const el = document.getElementById('modalPegawaiForm');
                if (el) {
                    new bootstrap.Modal(el).show();
                }
            });
        </script>
    @endscript
</div>
