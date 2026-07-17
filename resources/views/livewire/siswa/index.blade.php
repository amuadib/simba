@section('title', 'Data Siswa')

<?php

use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\Tag;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SiswaImport;
use App\Exports\SiswaExport;

new class extends \Livewire\Volt\Component {
    use WithPagination, WithFileUploads;

    // Filter & Search
    public $q = '';
    public $rombel_id = '';
    public $status = '';
    public $tag_id = '';

    // Action State
    public $action = ''; // '', 'edit', 'show'
    public $editingId = null;
    public $showId = null;

    // Form Fields
    public $nama, $panggilan, $jenis_kelamin, $nisn, $form_rombel_id, $form_status;
    public $selectedTags = [];
    public $newTags = [];

    // Selection for Export
    public $selectedSiswaCount = 0;

    // Bulk Actions
    public $bulkRombelId = '';
    public $bulkStatus = '';
    public $bulkTags = [];

    // Import
    public $importFile;
    public $importResults = [];

    public $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->selectedSiswaCount = count(session('selected_siswa', []));
    }

    public function updated($property)
    {
        if (in_array($property, ['q', 'rombel_id', 'status', 'tag_id'])) {
            $this->resetPage();
        }
    }

    // --- CRUD ACTIONS ---

    public function create()
    {
        $this->resetForm();
        $this->action = 'create';
    }

    public function store()
    {
        $data = $this->validate([
            'nama' => 'required',
            'panggilan' => 'nullable',
            'jenis_kelamin' => 'nullable',
            'nisn' => 'nullable',
            'form_status' => 'required|in:1,2,3,4,5,6',
            'form_rombel_id' => 'required',
        ]);

        $siswa = Siswa::create([
            'nama' => $this->nama,
            'panggilan' => $this->panggilan,
            'jenis_kelamin' => $this->jenis_kelamin,
            'nisn' => $this->nisn,
            'status' => $this->form_status,
            'rombel_id' => $this->form_rombel_id,
        ]);

        $tagIds = [];
        foreach ($this->selectedTags as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $tag = Tag::firstOrCreate(['nama' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $siswa->tags()->sync($tagIds);

        $this->resetForm();
        $this->dispatch('toast', message: 'Siswa berhasil ditambahkan', type: 'success');
    }

    public function show($id)
    {
        $this->editingId = null;
        $this->showId = $id;
        $this->action = 'show';
    }

    public function edit($id)
    {
        $this->showId = null;
        $this->editingId = $id;
        $this->action = 'edit';

        $siswa = Siswa::with('tags')->findOrFail($id);
        $this->nama = $siswa->nama;
        $this->panggilan = $siswa->panggilan ?? $siswa->nama;
        $this->jenis_kelamin = $siswa->jenis_kelamin;
        $this->nisn = $siswa->nisn;
        $this->form_status = $siswa->status;
        $this->form_rombel_id = $siswa->rombel_id;
        $this->selectedTags = $siswa->tags->pluck('nama')->toArray();
        $this->newTags = [];
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required',
            'panggilan' => 'nullable',
            'jenis_kelamin' => 'nullable',
            'nisn' => 'nullable',
            'form_status' => 'required|in:1,2,3,4,5,6',
            'form_rombel_id' => 'required',
        ]);

        $siswa = Siswa::findOrFail($this->editingId);
        $siswa->update([
            'nama' => $this->nama,
            'panggilan' => $this->panggilan,
            'jenis_kelamin' => $this->jenis_kelamin,
            'nisn' => $this->nisn,
            'status' => $this->form_status,
            'rombel_id' => $this->form_rombel_id,
        ]);

        $tagIds = [];
        foreach ($this->selectedTags as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $tag = Tag::firstOrCreate(['nama' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $siswa->tags()->sync($tagIds);

        $this->resetForm();
        $this->dispatch('toast', message: 'Siswa berhasil diperbarui', type: 'success');
    }

    public function delete($id)
    {
        $siswa = Siswa::findOrFail($id);
        $siswa->tags()->detach();
        $siswa->delete();
        $this->dispatch('toast', message: 'Siswa berhasil dihapus', type: 'success');
    }

    public function resetForm()
    {
        $this->reset(['nama', 'panggilan', 'jenis_kelamin', 'nisn', 'form_rombel_id', 'form_status', 'selectedTags', 'newTags', 'action', 'editingId', 'showId']);
    }

    // --- SELECTION & EXPORT ---

    public function pilihSiswa($id)
    {
        $selected = session('selected_siswa', []);
        if (!in_array($id, $selected)) {
            session()->push('selected_siswa', $id);
            $this->selectedSiswaCount = count(session('selected_siswa'));
            $this->dispatch('toast', message: 'Siswa berhasil dipilih', type: 'success');
        }
    }

    public function bulkUpdate()
    {
        $this->validate([
            'bulkRombelId' => 'nullable',
            'bulkStatus' => 'nullable',
            'bulkTags' => 'array',
        ]);

        if (empty($this->bulkRombelId) && empty($this->bulkStatus) && empty($this->bulkTags)) {
            $this->dispatch('toast', message: 'Pilih minimal satu data yang ingin diperbarui secara massal', type: 'error');
            return;
        }

        $query = Siswa::query()
            ->when($this->rombel_id, fn($q, $id) => $q->where('rombel_id', $id))
            ->when($this->tag_id, fn($q, $id) => $q->whereHas('tags', fn($q) => $q->where('tag_id', $id)))
            ->when($this->status, fn($q, $s) => $q->where('status', $s))
            ->when($this->q, fn($q, $search) => $q->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('panggilan', 'like', "%{$search}%");
            }));

        $siswaListToUpdate = $query->get();

        if ($siswaListToUpdate->isEmpty()) {
            $this->dispatch('toast', message: 'Tidak ada data siswa untuk diperbarui', type: 'warning');
            return;
        }

        foreach ($siswaListToUpdate as $s) {
            $updateData = [];
            if (!empty($this->bulkRombelId)) {
                $updateData['rombel_id'] = $this->bulkRombelId;
            }
            if (!empty($this->bulkStatus)) {
                $updateData['status'] = $this->bulkStatus;
            }

            if (!empty($updateData)) {
                $s->update($updateData);
            }

            if (!empty($this->bulkTags)) {
                $tagIds = [];
                foreach ($this->bulkTags as $name) {
                    $name = trim($name);
                    if (!empty($name)) {
                        $tag = Tag::firstOrCreate(['nama' => $name]);
                        $tagIds[] = $tag->id;
                    }
                }
                if (!empty($tagIds)) {
                    $s->tags()->syncWithoutDetaching($tagIds);
                }
            }
        }

        $this->reset(['bulkRombelId', 'bulkStatus', 'bulkTags']);
        $this->dispatch('close-modal', id: 'modalBulkUpdate');
        $this->dispatch('toast', message: 'Data siswa berhasil diperbarui secara massal', type: 'success');
    }

    public function import()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new SiswaImport();
        Excel::import($import, $this->importFile->getRealPath());
        $this->importResults = $import->getResults();
        $errorsCount = count($this->importResults['errors']);
        $message = "Impor data siswa selesai. Sukses: {$this->importResults['success']}" . ($errorsCount > 0 ? ", Gagal: {$errorsCount}" : "");
        $this->reset(['importFile']);
        $this->dispatch('toast', message: $message, type: $errorsCount > 0 ? 'warning' : 'success');
    }

    public function exportTemplate()
    {
        return Excel::download(new SiswaExport([], 'template-import'), 'SIMBA-template-import-siswa-'.date('YmdHis').'.xlsx');
    }
    // --- DATA FETCHING ---

    public function with()
    {
        $siswa = Siswa::with('rombel', 'tags')
            ->orderBy('nama')
            ->when($this->rombel_id, fn($q, $id) => $q->where('rombel_id', $id))
            ->when($this->tag_id, fn($q, $id) => $q->whereHas('tags', fn($q) => $q->where('tag_id', $id)))
            ->when($this->status, fn($q, $s) => $q->where('status', $s))
            ->when($this->q, fn($q, $search) => $q->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('panggilan', 'like', "%{$search}%");
            }))
            ->paginate(25);

        $bulkCount = Siswa::query()
            ->when($this->rombel_id, fn($q, $id) => $q->where('rombel_id', $id))
            ->when($this->tag_id, fn($q, $id) => $q->whereHas('tags', fn($q) => $q->where('tag_id', $id)))
            ->when($this->status, fn($q, $s) => $q->where('status', $s))
            ->when($this->q, fn($q, $search) => $q->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('panggilan', 'like', "%{$search}%");
            }))
            ->count();

        return [
            'siswaList' => $siswa,
            'rombels' => Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))->orderBy('tingkat')->get(),
            'tags' => Tag::orderBy('nama')->get(),
            'statusOptions' => config('local.status_siswa'),
            'selectedSiswa' => session('selected_siswa', []),
            'allTags' => Tag::orderBy('nama')->pluck('nama')->toArray(),
            'bulkCount' => $bulkCount,
        ];
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Siswa</h4>
            <small class="text-muted">Kelola data siswa</small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">

            {{-- TABS AREA --}}
            <div x-data="{ activeTab: 'filter' }" x-init="$watch('$wire.action', value => { if(value === 'edit' || value === 'create' || value === 'show') activeTab = 'form' })">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'filter' }" @click="activeTab = 'filter'" type="button" role="tab">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'form' }" @click="activeTab = 'form'" type="button" role="tab">
                            <i class="bi bi-pencil-square me-1"></i> Form Siswa
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'import' }" @click="activeTab = 'import'" type="button" role="tab">
                            <i class="bi bi-upload me-1"></i> Import
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" :class="{ 'active': activeTab === 'sync' }" @click="activeTab = 'sync'" type="button" role="tab">
                            <i class="bi bi-arrow-repeat me-1"></i> Sinkron
                        </button>
                    </li>

                    {{-- TOMBOL AKSI SEJAJAR TAB --}}
                    <li class="nav-item ms-auto d-flex align-items-center gap-2 pe-1 pb-1">
                        @if ($siswaList->count() > 0 && ($q != '' || $rombel_id != '' || $status != '' || $tag_id != ''))
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalBulkUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Update Massal
                            </button>
                        @endif

                        <button type="button" class="btn btn-outline-info btn-sm position-relative" data-bs-toggle="modal"
                            data-bs-target="#modalPreviewExport" id="btnPreviewExport">
                            <i class="bi bi-eye me-1"></i> Preview Ekspor
                            @if ($selectedSiswaCount > 0)
                                <span class="position-absolute start-100 translate-middle badge rounded-pill bg-danger top-0">
                                    {{ $selectedSiswaCount }}
                                </span>
                            @endif
                        </button>
                    </li>
                </ul>

                <div class="tab-content mb-4">
                    {{-- FORM AREA --}}
                    <div x-show="activeTab === 'form'" style="display: none;">
                        @if ($action == 'edit' || $action == 'create')
                            <div class="bg-primary bg-opacity-10 rounded border p-3">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="bi {{ $action == 'edit' ? 'bi-pencil-square' : 'bi-plus-circle' }} me-1"></i>
                                    {{ $action == 'edit' ? 'Edit' : 'Tambah' }} Data Siswa
                                </h6>
                                <form wire:submit="{{ $action == 'edit' ? 'update' : 'store' }}" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Nama Lengkap</label>
                                        <input wire:model="nama" class="form-control" placeholder="Nama Lengkap" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Panggilan</label>
                                        <input wire:model="panggilan" class="form-control" placeholder="Panggilan">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">JK</label>
                                        <div class="d-flex gap-3 pt-2">
                                            <div class="form-check">
                                                <input wire:model="jenis_kelamin" class="form-check-input" type="radio"
                                                    value="L" id="jkL">
                                                <label class="form-check-label" for="jkL">L</label>
                                            </div>
                                            <div class="form-check">
                                                <input wire:model="jenis_kelamin" class="form-check-input" type="radio"
                                                    value="P" id="jkP">
                                                <label class="form-check-label" for="jkP">P</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">NISN</label>
                                        <input wire:model="nisn" class="form-control" placeholder="NISN">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Rombel</label>
                                        <select wire:model="form_rombel_id" class="form-select" required>
                                            <option value="">--Pilih--</option>
                                            @foreach ($rombels as $r)
                                                <option value="{{ $r->id }}">{{ $r->nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Status</label>
                                        <select wire:model="form_status" class="form-select" required>
                                            <option value="">--Pilih--</option>
                                            @foreach ($statusOptions as $k => $v)
                                                <option value="{{ $k }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Tag</label>
                                        <div x-data="{
                                            open: false,
                                            text: '',
                                            tags: @entangle('selectedTags'),
                                            allTags: @js($allTags),
                                            get suggestions(){
                                                if(this.text.trim() === '') return [];
                                                return this.allTags.filter(t => t.toLowerCase().includes(this.text.toLowerCase()) && !this.tags.includes(t));
                                            },
                                            addTag(tag){
                                                tag = tag.trim();
                                                if(tag && !this.tags.includes(tag)){
                                                    this.tags.push(tag);
                                                }
                                                this.text = '';
                                                this.open = false;
                                            },
                                            removeTag(index){
                                                this.tags.splice(index, 1);
                                            }
                                        }" class="position-relative">
                                            <div class="form-control d-flex flex-wrap gap-1 align-items-center" :class="{ 'border-success': open }" style="min-height: 38px;">
                                                <template x-for="(tag, index) in tags" :key="index">
                                                    <span class="badge bg-primary d-inline-flex align-items-center rounded-1">
                                                        <span x-text="tag"></span>
                                                        <button type="button" @click="removeTag(index)" class="btn-close btn-close-white ms-2" style="font-size: 0.5em;" aria-label="Remove"></button>
                                                    </span>
                                                </template>
                                                <input type="text" x-model="text" @keydown.enter.prevent="addTag(text)"
                                                        @keydown.comma.prevent="addTag(text)" @keydown.escape="open = false"
                                                        @focus="open = true" @click.away="open = false"
                                                        class="border-0 p-0 m-0 flex-grow-1 bg-transparent text-sm"
                                                        style="outline: none; min-width: 100px; box-shadow: none;"
                                                        placeholder="">
                                            </div>
                                            <div x-show="open && suggestions.length > 0" x-cloak
                                                 class="position-absolute z-3 mt-1 w-100 bg-body border rounded shadow-sm overflow-auto" style="max-height: 200px;">
                                                <template x-for="suggestion in suggestions" :key="suggestion">
                                                    <div @click="addTag(suggestion)"
                                                         class="px-3 py-2 text-body border-bottom" style="cursor: pointer;"
                                                         onmouseover="this.classList.add('bg-secondary', 'bg-opacity-10')" onmouseout="this.classList.remove('bg-secondary', 'bg-opacity-10')">
                                                        <span x-text="suggestion"></span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="form-text small text-muted">Tekan Enter atau Koma untuk menambah tag</div>
                                        </div>
                                    </div>

                                    <div class="col-md-10 d-flex align-items-end gap-2">
                                        <button type="submit" class="btn btn-{{ $action == 'edit' ? 'warning' : 'primary' }}">
                                            <i class="bi bi-save me-1"></i> SIMPAN
                                        </button>
                                        <button type="button" wire:click="resetForm" class="btn btn-secondary">BATAL</button>
                                    </div>
                                </form>
                            </div>
                        @elseif($action == 'show')
                            <div class="bg-info rounded border bg-opacity-10 p-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-eye me-1"></i> Detail Siswa</h6>
                                @php $sData = App\Models\Siswa::find($showId); @endphp
                                @if ($sData)
                                    <div class="row g-3">
                                        <div class="col-md-3"><strong>Nama</strong><br>{{ $sData->nama }}</div>
                                        <div class="col-md-2"><strong>Panggilan</strong><br>{{ $sData->panggilan ?? '-' }}</div>
                                        <div class="col-md-2"><strong>JK</strong><br>{{ $sData->jenis_kelamin }}</div>
                                        <div class="col-md-2"><strong>NISN</strong><br>{{ $sData->nisn ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Rombel</strong><br>{{ $sData->rombel->nama }}</div>
                                        <div class="col-12"><button wire:click="resetForm"
                                                class="btn btn-sm btn-secondary">Tutup</button></div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="d-flex justify-content-between align-items-center">
                                <button wire:click="create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>
                                    Tambah Siswa</button>
                            </div>
                        @endif
                    </div>

                    {{-- FILTER AREA --}}
                    <div x-show="activeTab === 'filter'">
                        <div class="bg-light rounded border p-3">
                            <form wire:submit.prevent class="row g-2">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0 bg-white"><i
                                                class="bi bi-search text-muted"></i></span>
                                        <input type="text" wire:model.live.debounce.300ms="q"
                                            class="form-control border-start-0" placeholder="Cari nama siswa...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="rombel_id" class="form-select">
                                        <option value="">-- Semua Rombel --</option>
                                        @foreach ($rombels as $k)
                                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
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
                                    <select wire:model.live="tag_id" class="form-select">
                                        <option value="">-- Semua Tag --</option>
                                        @foreach ($tags as $tag)
                                            <option value="{{ $tag->id }}">{{ $tag->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" wire:click="resetPage" class="btn btn-success w-100"><i
                                            class="bi bi-funnel me-1"></i> Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- IMPORT AREA --}}
                    <div x-show="activeTab === 'import'" style="display: none;">
                        <div class="bg-light rounded border p-3">
                            <form wire:submit="import" class="row g-2">
                                <div class="col-md-6">
                                    <input type="file" wire:model="importFile" class="form-control"
                                        accept=".xlsx,.csv" required>
                                    @error('importFile')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-warning w-100"
                                        wire:loading.attr="disabled">
                                        <i class="bi bi-upload"></i> IMPORT
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" wire:click="exportTemplate" class="btn btn-primary w-100"
                                        wire:loading.attr="disabled">
                                        <i class="bi bi-download"></i> TEMPLATE
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- SYNC AREA --}}
                    <div x-show="activeTab === 'sync'" style="display: none;">
                        <div class="bg-light rounded border p-3">
                            <form wire:submit.prevent="syncFromMasterApi" class="row g-2">
                                <div class="col-md-6">
                                    <button type="button" wire:click="syncFromMasterApi" class="btn btn-primary">
                                        <i class="bi bi-arrow-repeat me-1"></i> Sinkron dari Master Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="table-responsive">
                <table class="table-hover table border align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>NISN</th>
                            <th>Rombel</th>
                            <th>Status</th>
                            <th>Tag</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswaList as $s)
                            <tr wire:key="row-{{ $s->id }}">
                                <td>{{ $loop->iteration + ($siswaList->currentPage() - 1) * $siswaList->perPage() }}</td>
                                <td>
                                    {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                </td>
                                <td>{{ $s->nisn ?: '-' }}</td>
                                <td><span class="badge bg-secondary opacity-75">{{ $s->rombel->nama??'' }}</span></td>
                                <td>
                                    @php
                                        $badges = [
                                            1 => 'bg-success',
                                            2 => 'bg-info',
                                            3 => 'bg-warning',
                                            4 => 'bg-secondary',
                                            5 => 'bg-dark',
                                            6 => 'bg-danger',
                                        ];
                                    @endphp
                                    <span class="badge {{ $badges[$s->status] ?? 'bg-secondary' }}">
                                        {{ $statusOptions[$s->status] ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @foreach ($s->tags as $t)
                                        <span class="badge bg-light text-dark small me-1 border"
                                            style="font-size: 0.65rem">{{ $t->nama }}</span>
                                    @endforeach
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button wire:click="pilihSiswa('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-info {{ in_array($s->id, $selectedSiswa) ? 'bg-info text-white' : '' }}"
                                            title="Pilih untuk Ekspor">
                                            <i
                                                class="bi {{ in_array($s->id, $selectedSiswa) ? 'bi-check-lg' : 'bi-plus-lg' }}"></i>
                                        </button>
                                        <button wire:click="show('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></button>
                                        <button wire:click="edit('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-warning"><i
                                                class="bi bi-pencil"></i></button>
                                        <button wire:click="delete('{{ $s->id }}')"
                                            wire:confirm="Hapus data ini?" class="btn btn-sm btn-outline-danger"><i
                                                class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center">
                                    <i class="bi bi-search fs-2 text-muted d-block mb-2"></i>
                                    Tidak ditemukan data siswa
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $siswaList->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL BULK UPDATE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkUpdate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Data secara Massal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Perubahan akan diterapkan pada <strong>{{ $bulkCount }}</strong> siswa hasil filter saat ini.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kelas (Rombel)</label>
                        <select wire:model="bulkRombelId" class="form-select">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($rombels as $r)
                                <option value="{{ $r->id }}">{{ $r->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select wire:model="bulkStatus" class="form-select">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($statusOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tambah Tag</label>
                        <div x-data="{
                            open: false,
                            text: '',
                            tags: @entangle('bulkTags'),
                            allTags: @js($allTags),
                            get suggestions(){
                                if(this.text.trim() === '') return [];
                                return this.allTags.filter(t => t.toLowerCase().includes(this.text.toLowerCase()) && !this.tags.includes(t));
                            },
                            addTag(tag){
                                tag = tag.trim();
                                if(tag && !this.tags.includes(tag)){
                                    this.tags.push(tag);
                                }
                                this.text = '';
                                this.open = false;
                            },
                            removeTag(index){
                                this.tags.splice(index, 1);
                            }
                        }" class="position-relative">
                            <div class="form-control d-flex flex-wrap gap-1 align-items-center" :class="{ 'border-success': open }" style="min-height: 38px;">
                                <template x-for="(tag, index) in tags" :key="index">
                                    <span class="badge bg-primary d-inline-flex align-items-center rounded-1">
                                        <span x-text="tag"></span>
                                        <button type="button" @click="removeTag(index)" class="btn-close btn-close-white ms-2" style="font-size: 0.5em;" aria-label="Remove"></button>
                                    </span>
                                </template>
                                <input type="text" x-model="text" @keydown.enter.prevent="addTag(text)"
                                        @keydown.comma.prevent="addTag(text)" @keydown.escape="open = false"
                                        @focus="open = true" @click.away="open = false"
                                        class="border-0 p-0 m-0 flex-grow-1 bg-transparent text-sm"
                                        style="outline: none; min-width: 100px; box-shadow: none;"
                                        placeholder="">
                            </div>
                            <div x-show="open && suggestions.length > 0" x-cloak
                                 class="position-absolute z-3 mt-1 w-100 bg-body border rounded shadow-sm overflow-auto" style="max-height: 200px;">
                                <template x-for="suggestion in suggestions" :key="suggestion">
                                    <div @click="addTag(suggestion)"
                                         class="px-3 py-2 text-body border-bottom" style="cursor: pointer;"
                                         onmouseover="this.classList.add('bg-secondary', 'bg-opacity-10')" onmouseout="this.classList.remove('bg-secondary', 'bg-opacity-10')">
                                        <span x-text="suggestion"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="form-text small text-muted">Tekan Enter atau Koma untuk menambah tag</div>
                        </div>
                    </div>
                    <button wire:click="bulkUpdate" class="btn btn-primary w-100" wire:loading.attr="disabled">
                        <i class="bi bi-save me-1"></i> SIMPAN PERUBAHAN MASSAL
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL PREVIEW EXPORT --}}
    <div wire:ignore.self class="modal fade" id="modalPreviewExport" data-bs-backdrop="static" tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i> Preview Ekspor Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="previewExportContent" wire:ignore>
                    <div class="py-5 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="text-muted mt-2">Memuat data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @script
        <script>
            $wire.on('close-modal', ({
                id
            }) => {
                bootstrap.Modal.getInstance(document.getElementById(id))?.hide();
            });

            document.getElementById('modalPreviewExport').addEventListener('show.bs.modal', function() {
                loadPreviewExport();
            });

            function loadPreviewExport() {
                const content = document.getElementById('previewExportContent');
                fetch("{{ route('siswa.preview-export') }}", {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        content.innerHTML = data.html;
                        attachPreviewListeners();
                    });
            }

            function attachPreviewListeners() {
                document.querySelectorAll('.btn-hapus-preview').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('Hapus dari daftar ekspor?')) {
                            fetch("{{ url('/siswa/preview-export') }}/" + id, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    loadPreviewExport();
                                    $wire.set('selectedSiswaCount', data.count);
                                });
                        }
                    });
                });

                const btnKosongkan = document.getElementById('btn-kosongkan-preview');
                if (btnKosongkan) {
                    btnKosongkan.addEventListener('click', function() {
                        if (confirm('Kosongkan semua daftar pilihan?')) {
                            fetch("{{ url('/siswa/preview-export/all') }}", {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    loadPreviewExport();
                                    $wire.set('selectedSiswaCount', 0);
                                });
                        }
                    });
                }
            }
        </script>
    @endscript
