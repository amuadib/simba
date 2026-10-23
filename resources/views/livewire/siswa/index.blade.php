@section('title', 'Data Siswa')

<?php

use App\Models\Siswa;
use App\Models\SiswaHistory;
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
    public $nik, $nis, $no_akte, $no_kk, $tempat_lahir, $tanggal_lahir, $lembaga_id, $alamat, $telepon, $ayah, $ibu, $foto, $existing_foto;
    public $selectedTags = [];

    // Checkbox Selection
    public $checkedSiswa = [];
    public $selectAll = false;
    public $randomConfirmString = '';
    public $confirmText = '';

    // Bulk Actions
    public $bulkRombelId = '';
    public $bulkStatus = '';
    public $bulkTags = [];
    public $showBulkUpdateModal = false;
    public $showBulkDeleteModal = false;

    public $selected_siswa_id = '';

    // History State
    public $historySiswaId = null;
    public $historyActionFilter = '';
    public $historySearch = '';

    // Import
    public $importFile;

    public $paginationTheme = 'bootstrap';

    public function updated($property)
    {
        if (in_array($property, ['q', 'rombel_id', 'status', 'tag_id'])) {
            $this->resetPage();
            $this->checkedSiswa = [];
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->checkedSiswa = Siswa::query()
                ->when($this->rombel_id, fn($q, $id) => $q->where('rombel_id', $id))
                ->when($this->tag_id, fn($q, $id) => $q->whereHas('tags', fn($q) => $q->where('tag_id', $id)))
                ->when($this->status, fn($q, $s) => $q->where('status', $s))
                ->when($this->q, fn($q, $search) => $q->where(function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('panggilan', 'like', "%{$search}%");
                }))->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->checkedSiswa = [];
        }
    }

    public function updatedCheckedSiswa()
    {
        $this->selectAll = false;
    }

    public function prepareBulkDelete()
    {
        $this->randomConfirmString = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $this->confirmText = '';
        $this->showBulkDeleteModal = true;
    }

    public function cancelBulkDelete()
    {
        $this->showBulkDeleteModal = false;
        $this->randomConfirmString = '';
        $this->confirmText = '';
    }

    public function bulkDelete()
    {
        if (strtoupper($this->confirmText) !== $this->randomConfirmString) {
            $this->dispatch('toast', message: 'Karakter konfirmasi tidak cocok', type: 'error');
            return;
        }

        $siswas = Siswa::whereIn('id', $this->checkedSiswa)->get();
        foreach ($siswas as $s) {
            $s->tags()->detach();
            $s->delete();
        }

        $this->checkedSiswa = [];
        $this->selectAll = false;
        $this->showBulkDeleteModal = false;
        $this->dispatch('toast', message: 'Data siswa berhasil dihapus', type: 'success');
    }

    public function bulkExport()
    {
        return Excel::download(new SiswaExport($this->checkedSiswa, 'data'), 'SIMBA-ekspor-siswa-'.date('YmdHis').'.xlsx');
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
            'foto' => 'nullable|image|max:2048',
        ]);

        $siswa = Siswa::create([
            'nama' => $this->nama,
            'panggilan' => $this->panggilan,
            'jenis_kelamin' => $this->jenis_kelamin,
            'nisn' => $this->nisn,
            'status' => $this->form_status,
            'rombel_id' => $this->form_rombel_id,
            'nik' => $this->nik,
            'nis' => $this->nis,
            'no_akte' => $this->no_akte,
            'no_kk' => $this->no_kk,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'lembaga_id' => $this->lembaga_id ?: 99,
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'ayah' => $this->ayah,
            'ibu' => $this->ibu,
            'foto' => $this->foto ? $this->foto->store('siswa', 'public') : null,
        ]);

        $tagIds = [];
        foreach ($this->selectedTags as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $tag = Tag::firstOrCreate(['nama' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $siswa->syncTagsWithHistory($tagIds, 'create');

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
        $this->nik = $siswa->nik;
        $this->nis = $siswa->nis;
        $this->no_akte = $siswa->no_akte;
        $this->no_kk = $siswa->no_kk;
        $this->tempat_lahir = $siswa->tempat_lahir;
        $this->tanggal_lahir = $siswa->tanggal_lahir;
        $this->lembaga_id = $siswa->lembaga_id;
        $this->alamat = $siswa->alamat;
        $this->telepon = $siswa->telepon;
        $this->ayah = $siswa->ayah;
        $this->ibu = $siswa->ibu;
        $this->existing_foto = $siswa->foto;
        $this->foto = null;
        $this->selectedTags = $siswa->tags->pluck('nama')->toArray();
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
            'foto' => 'nullable|image|max:2048',
        ]);

        $siswa = Siswa::findOrFail($this->editingId);

        $fotoPath = $siswa->foto;
        if ($this->foto) {
            if ($siswa->foto && \Storage::disk('public')->exists($siswa->foto)) {
                \Storage::disk('public')->delete($siswa->foto);
            }
            $fotoPath = $this->foto->store('siswa', 'public');
        }

        $siswa->update([
            'nama' => $this->nama,
            'panggilan' => $this->panggilan,
            'jenis_kelamin' => $this->jenis_kelamin,
            'nisn' => $this->nisn,
            'status' => $this->form_status,
            'rombel_id' => $this->form_rombel_id,
            'nik' => $this->nik,
            'nis' => $this->nis,
            'no_akte' => $this->no_akte,
            'no_kk' => $this->no_kk,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'lembaga_id' => $this->lembaga_id ?: 99,
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'ayah' => $this->ayah,
            'ibu' => $this->ibu,
            'foto' => $fotoPath,
        ]);

        $tagIds = [];
        foreach ($this->selectedTags as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $tag = Tag::firstOrCreate(['nama' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $siswa->syncTagsWithHistory($tagIds, 'update');

        $this->resetForm();
        $this->dispatch('toast', message: 'Siswa berhasil diperbarui', type: 'success');
    }


    public function resetForm()
    {
        $this->reset(['nama', 'panggilan', 'jenis_kelamin', 'nisn', 'form_rombel_id', 'form_status', 'selectedTags', 'action', 'editingId', 'showId', 'nik', 'nis', 'no_akte', 'no_kk', 'tempat_lahir', 'tanggal_lahir', 'lembaga_id', 'alamat', 'telepon', 'ayah', 'ibu', 'foto', 'existing_foto']);
    }

    public function openBulkUpdate()
    {
        $this->showBulkUpdateModal = true;
    }

    public function closeBulkUpdate()
    {
        $this->showBulkUpdateModal = false;
        $this->bulkRombelId = '';
        $this->bulkStatus = '';
        $this->bulkTags = [];
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

        $siswaListToUpdate = Siswa::whereIn('id', $this->checkedSiswa)->get();

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
                $s->historyAction = 'bulk_update';
                $s->historyDescription = 'Pembaruan data siswa secara massal';
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
                    $currentTagIds = $s->tags()->pluck('tag_id')->toArray();
                    $allTagIds = array_values(array_unique(array_merge($currentTagIds, $tagIds)));
                    $s->syncTagsWithHistory($allTagIds, 'bulk_update', 'Pembaruan tag secara massal');
                }
            }
        }

        $this->reset(['bulkRombelId', 'bulkStatus', 'bulkTags', 'checkedSiswa', 'selectAll']);
        $this->showBulkUpdateModal = false;
        $this->dispatch('toast', message: 'Data siswa berhasil diperbarui secara massal', type: 'success');
    }

    // --- RIWAYAT PERUBAHAN & UNDO ---

    public function openHistory($id)
    {
        $this->historySiswaId = $id;
    }

    public function closeHistory()
    {
        $this->historySiswaId = null;
    }

    public function undoHistory($historyId)
    {
        $history = SiswaHistory::findOrFail($historyId);
        $siswa = Siswa::findOrFail($history->siswa_id);

        if ($history->is_reverted) {
            $this->dispatch('toast', message: 'Perubahan ini sudah pernah dibatalkan (undo) sebelumnya.', type: 'warning');
            return;
        }

        if ($history->action === 'create' || empty($history->old_values)) {
            $this->dispatch('toast', message: 'Data penambahan awal tidak dapat di-undo.', type: 'error');
            return;
        }

        $success = $siswa->rollbackHistory($history);
        if ($success) {
            $this->dispatch('toast', message: 'Perubahan data siswa berhasil dibatalkan (undo).', type: 'success');
        } else {
            $this->dispatch('toast', message: 'Gagal meng-undo perubahan data.', type: 'error');
        }
    }

    public function undoLatestHistory($siswaId)
    {
        $latest = SiswaHistory::where('siswa_id', $siswaId)
            ->where('is_reverted', false)
            ->whereIn('action', ['update', 'bulk_update', 'rollback'])
            ->whereNotNull('old_values')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            $this->dispatch('toast', message: 'Tidak ada perubahan yang dapat di-undo untuk siswa ini.', type: 'warning');
            return;
        }

        $this->undoHistory($latest->id);
    }

    public function import()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new SiswaImport();
        Excel::import($import, $this->importFile->getRealPath());
        $results = $import->getResults();
        $errorsCount = count($results['errors']);
        $message = "Impor data siswa selesai. Sukses: {$results['success']}" . ($errorsCount > 0 ? ", Gagal: {$errorsCount}" : "");
        $this->reset(['importFile']);
        $this->dispatch('toast', message: $message, type: $errorsCount > 0 ? 'warning' : 'success');
    }

    public function exportTemplate()
    {
        return Excel::download(new SiswaExport([], 'template-import'), 'SIMBA-template-import-siswa-'.date('YmdHis').'.xlsx');
    }

    public function exportCashout($ids)
    {
        return Excel::download(new SiswaExport($ids, 'cashout-template'), 'SIMBA-cashout-siswa-'.date('YmdHis').'.xlsx');
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

        return [
            'siswaList' => $siswa,
            'rombels' => Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))->orderBy('tingkat')->get(),
            'tags' => Tag::orderBy('nama')->get(),
            'statusOptions' => config('local.status_siswa'),
            'allTags' => Tag::orderBy('nama')->pluck('nama')->toArray(),
            'historySiswa' => $this->historySiswaId ? Siswa::with(['histories.user', 'histories.reverter', 'rombel'])->find($this->historySiswaId) : null,
            'globalHistories' => SiswaHistory::with(['siswa.rombel', 'user', 'reverter'])
                ->when($this->historyActionFilter, fn($q, $a) => $q->where('action', $a))
                ->when($this->historySearch, fn($q, $s) => $q->whereHas('siswa', fn($sq) => $sq->where('nama', 'like', "%{$s}%")->orWhere('nisn', 'like', "%{$s}%")))
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'history_page'),
            'siswaAktif' => Siswa::with('rombel', 'tags')->where('status', 1)->orderBy('nama')->get()->map(function($s) {
                return [
                    'id' => $s->id,
                    'nama' => $s->nama,
                    'panggilan' => $s->panggilan,
                    'rombel' => $s->rombel->nama ?? '-',
                    'nisn' => $s->nisn,
                    'tags' => $s->tags->pluck('nama')->implode(', ')
                ];
            })->toArray(),
        ];
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            @if ($action === 'create')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Tambah Data Siswa</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Isi formulir lengkap untuk mendaftarkan peserta didik baru</p>
            @elseif ($action === 'edit')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Edit Data Siswa</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Perbarui informasi data peserta didik {{ $nama }}</p>
            @elseif ($action === 'show')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Detail Profil Siswa</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Informasi lengkap peserta didik</p>
            @else
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Data Siswa</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola data peserta didik, rombongan belajar & status keaktifan</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($action !== '')
                <button type="button" wire:click="resetForm" class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                </button>
            @endif
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    @if ($action === '')
    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div>

            {{-- TABS AREA --}}
            <div x-data="{ activeTab: 'filter' }">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/80 dark:border-slate-800">
                    
                    {{-- Nav Tabs Pills --}}
                    <div class="inline-flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/90 dark:border-slate-700/80 shadow-xs self-start">
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'filter' 
                                    ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' 
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60'"
                                @click="activeTab = 'filter'" type="button">
                            <i class="bi bi-funnel-fill text-sky-500 dark:text-cyan-400"></i>
                            <span>Filter</span>
                        </button>
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'import' 
                                    ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' 
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60'"
                                @click="activeTab = 'import'" type="button">
                            <i class="bi bi-upload text-sky-500 dark:text-cyan-400"></i>
                            <span>Import</span>
                        </button>
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'cashout-template' 
                                    ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' 
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60'"
                                @click="activeTab = 'cashout-template'" type="button">
                            <i class="bi bi-file-earmark-excel-fill text-emerald-500"></i>
                            <span>Template Cashout</span>
                        </button>
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'history' 
                                    ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' 
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60'"
                                @click="activeTab = 'history'" type="button">
                            <i class="bi bi-clock-history text-cyan-500"></i>
                            <span>Riwayat Perubahan</span>
                        </button>
                    </div>

                    {{-- TOMBOL AKSI SEJAJAR TAB --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        @if (count($checkedSiswa) > 0)
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="openBulkUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Update ({{ count($checkedSiswa) }})
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="prepareBulkDelete">
                                <i class="bi bi-trash me-1"></i> Hapus ({{ count($checkedSiswa) }})
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="bulkExport">
                                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ({{ count($checkedSiswa) }})
                            </button>
                        @endif

                        <button wire:click="create" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                            <i class="bi bi-plus-circle text-sm"></i> Tambah Siswa
                        </button>
                    </div>
                </div>

                <div class="tab-content mb-4">
                    <div x-show="activeTab === 'cashout-template'">
                        <div class="rounded p-3" style="background-color: var(--table-bg); border: 1px solid var(--table-border);">
                            <div class="mb-3" x-data="{
                                open: false,
                                search: '',
                                options: @js($siswaAktif),
                                highlightedIndex: 0,
                                cashoutList: JSON.parse(localStorage.getItem('simba_cashoutList') || '[]'),
                                get filteredOptions() {
                                    if (this.search.length < 3) return [];
                                    const s = this.search.toLowerCase();
                                    return this.options.filter(o =>
                                        o.nama.toLowerCase().includes(s) ||
                                        (o.panggilan && o.panggilan.toLowerCase().includes(s)) ||
                                        (o.nisn && o.nisn.toLowerCase().includes(s))
                                    ).slice(0, 20);
                                },
                                selectOption(opt) {
                                    if (opt) {
                                        if (!this.cashoutList.find(c => c.id === opt.id)) {
                                            this.cashoutList.push(opt);
                                            localStorage.setItem('simba_cashoutList', JSON.stringify(this.cashoutList));
                                        }
                                        this.search = '';
                                        this.highlightedIndex = 0;
                                        this.open = true;
                                        this.$nextTick(() => {
                                            this.$refs.searchInput.focus();
                                        });
                                    }
                                },
                                onKeydown(e) {
                                    if (!this.open) {
                                        if(e.key === 'Enter') {
                                            e.preventDefault();
                                            this.open = true;
                                            this.$nextTick(() => this.$refs.searchInput.focus());
                                        }
                                        return;
                                    }
                                    const items = this.filteredOptions;
                                    if(e.key === 'ArrowDown') {
                                        e.preventDefault();
                                        if(this.highlightedIndex < items.length - 1) this.highlightedIndex++;
                                        this.scrollToActive();
                                    } else if(e.key === 'ArrowUp') {
                                        e.preventDefault();
                                        if(this.highlightedIndex > 0) this.highlightedIndex--;
                                        this.scrollToActive();
                                    } else if(e.key === 'Enter') {
                                        e.preventDefault();
                                        if(items.length > 0 && items[this.highlightedIndex]) {
                                            this.selectOption(items[this.highlightedIndex]);
                                        }
                                    }
                                },
                                scrollToActive() {
                                    this.$nextTick(() => {
                                        const el = this.$refs.listbox;
                                        const active = el.querySelector('.is-active');
                                        if (active) {
                                            if (active.offsetTop < el.scrollTop) {
                                                el.scrollTop = active.offsetTop;
                                            } else if (active.offsetTop + active.offsetHeight > el.scrollTop + el.clientHeight) {
                                                el.scrollTop = active.offsetTop + active.offsetHeight - el.clientHeight;
                                            }
                                        }
                                    });
                                },
                                removeSiswa(id) {
                                    this.cashoutList = this.cashoutList.filter(s => s.id !== id);
                                    localStorage.setItem('simba_cashoutList', JSON.stringify(this.cashoutList));
                                }
                            }" x-init="$watch('search', () => highlightedIndex = 0)">
                                <label class="form-label fw-bold">Pilih Siswa</label>
                                <div class="position-relative" @click.away="open = false; search = ''">
                                    
                                    <!-- Select Trigger (Closed State) -->
                                    <div :class="!open ? 'd-flex' : 'd-none'" 
                                         @click="open = true; $nextTick(() => $refs.searchInput.focus())" 
                                         class="form-control justify-content-between align-items-center" 
                                         style="cursor: pointer; background-color: var(--bg-card); border-color: var(--table-border); color: var(--text-main);">
                                        <span class="text-muted">-- Ketik / Pilih Siswa untuk Ditambahkan --</span>
                                        <div class="border-start ps-2">
                                            <i class="bi bi-chevron-down text-muted"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Input Mode (Open State) -->
                                    <div :class="open ? 'd-flex' : 'd-none'" class="form-control align-items-center p-0 border-primary" style="cursor: text; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); background-color: var(--bg-card);">
                                        <input x-ref="searchInput" 
                                               type="text" 
                                               class="border-0 w-100 px-3 py-2 bg-transparent"
                                               style="outline: none; box-shadow: none; color: var(--text-main);"
                                               placeholder="Ketik nama atau NISN..."
                                               x-model="search"
                                               @keydown="onKeydown"
                                               @keydown.escape="open = false; search = ''">
                                        <div class="d-flex align-items-center px-2 border-start" @click.stop="open = false; search = ''" style="cursor: pointer;">
                                            <i class="bi bi-chevron-up text-muted"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Dropdown List -->
                                    <div x-show="open" style="display: none; z-index: 1050; background-color: var(--bg-card); border: 1px solid var(--table-border);" class="position-absolute w-100 mt-1 rounded shadow-sm">
                                        
                                        <!-- Header for search query -->
                                        <div x-show="search !== ''" class="p-2 bg-primary bg-opacity-10 text-primary border-bottom small">
                                            Cari &quot;<span x-text="search"></span>&quot;
                                        </div>

                                        <div class="overflow-auto" x-ref="listbox" style="max-height: 250px;">
                                            <template x-for="(opt, index) in filteredOptions" :key="opt.id">
                                                <div @click="selectOption(opt)" 
                                                     class="px-3 py-2 border-bottom"
                                                     style="cursor: pointer;"
                                                     :class="{
                                                         'bg-primary text-white is-active': highlightedIndex === index,
                                                         'hover-bg-light': highlightedIndex !== index
                                                     }"
                                                     @mouseenter="highlightedIndex = index">
                                                    <div class="text-uppercase" :class="highlightedIndex === index ? 'text-white' : 'text-primary'" style="color: #1e53a3;">
                                                        <span x-text="opt.nama"></span> (<span x-text="opt.panggilan"></span>) - <span x-text="opt.nisn || '-'"></span>
                                                    </div>
                                                    <small :class="highlightedIndex === index ? 'text-white-50' : 'text-muted'" x-text="(opt.rombel || '-') + (opt.tags ? ' , ' + opt.tags : '')"></small>
                                                </div>
                                            </template>
                                            <div x-show="filteredOptions.length === 0" class="p-3 text-center text-muted small">
                                                <span x-show="search.length < 3">Ketik minimal 3 karakter untuk mencari siswa...</span>
                                                <span x-show="search.length >= 3">Tidak ada siswa ditemukan</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-bordered table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">No</th>
                                                <th>Nama Siswa</th>
                                                <th>Panggilan</th>
                                                <th>NISN</th>
                                                <th>Rombel</th>
                                                <th style="width: 60px;" class="text-center"><i class="bi bi-trash"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(siswa, index) in cashoutList" :key="siswa.id">
                                                <tr>
                                                    <td class="text-center" x-text="index + 1"></td>
                                                    <td class="fw-bold text-uppercase text-primary" x-text="siswa.nama"></td>
                                                    <td x-text="siswa.panggilan || '-'"></td>
                                                    <td x-text="siswa.nisn || '-'"></td>
                                                    <td x-text="siswa.rombel || '-'"></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeSiswa(siswa.id)">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="cashoutList.length === 0">
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    Belum ada siswa terpilih untuk cashout
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-start mt-2">
                                    <button type="button" class="btn btn-success btn-sm" @click="if(cashoutList.length === 0) { alert('Belum ada siswa yang dipilih.'); return; } $wire.exportCashout(cashoutList.map(s => s.id))">
                                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm ms-2" @click="if(cashoutList.length === 0) { alert('Belum ada siswa yang dipilih.'); return; } if(confirm('Apakah Anda yakin ingin menghapus semua siswa dari daftar cashout ini?')) { cashoutList = []; localStorage.removeItem('simba_cashoutList'); }">
                                        <i class="bi bi-trash me-1"></i> Bersihkan Daftar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- FILTER AREA --}}
                    <div x-show="activeTab === 'filter'">
                        <div class="p-3.5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                            <form wire:submit.prevent class="row g-2.5 items-center">
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm shadow-xs rounded-xl overflow-hidden">
                                        <span class="input-group-text border-end-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-sky-500 ps-3">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="q"
                                            class="form-control form-control-sm border-start-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 text-xs py-2" placeholder="Cari nama siswa, NISN...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="rombel_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Rombel --</option>
                                        @foreach ($rombels as $k)
                                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="status" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Status --</option>
                                        @foreach ($statusOptions as $k => $v)
                                            <option value="{{ $k }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="tag_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Tag --</option>
                                        @foreach ($tags as $tag)
                                            <option value="{{ $tag->id }}">{{ $tag->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" wire:click="resetPage" class="w-100 py-2 px-2.5 rounded-xl bg-white hover:bg-rose-50 dark:bg-slate-900 dark:hover:bg-rose-950/40 text-slate-600 hover:text-rose-600 dark:text-slate-300 dark:hover:text-rose-400 border border-slate-200 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-xs" title="Reset Filter">
                                        <i class="bi bi-arrow-counterclockwise text-sm"></i>
                                        <span>Reset Filter</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- IMPORT AREA --}}
                    <div x-show="activeTab === 'import'" style="display: none;">
                        <div class="p-4 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                            <form wire:submit="import" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <input type="file" wire:model="importFile" class="form-control form-control-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl"
                                        accept=".xlsx,.csv" required>
                                    @error('importFile')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-warning btn-sm w-100 rounded-xl fw-bold py-2 shadow-xs"
                                        wire:loading.attr="disabled">
                                        <i class="bi bi-upload me-1"></i> IMPORT EXCEL
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="exportTemplate" class="btn btn-outline-primary btn-sm w-100 rounded-xl fw-bold py-2 shadow-xs"
                                        wire:loading.attr="disabled">
                                        <i class="bi bi-download me-1"></i> UNDUH TEMPLATE
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- LOG RIWAYAT SISWA GLOBAL AREA --}}
                    <div x-show="activeTab === 'history'" style="display: none;">
                        <div class="p-3.5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-3">
                            <div class="row g-2.5 items-center">
                                <div class="col-md-5">
                                    <div class="input-group input-group-sm shadow-xs rounded-xl overflow-hidden">
                                        <span class="input-group-text border-end-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-cyan-500 ps-3">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="historySearch"
                                            class="form-control form-control-sm border-start-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 text-xs py-2" placeholder="Cari nama siswa atau NISN...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select wire:model.live="historyActionFilter" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Jenis Aksi --</option>
                                        <option value="update">Pembaruan (Update)</option>
                                        <option value="bulk_update">Pembaruan Massal</option>
                                        <option value="rollback">Undo / Rollback</option>
                                        <option value="create">Penambahan Data</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="$set('historySearch', ''); $set('historyActionFilter', '')" class="w-100 py-2 px-2.5 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-xs" title="Reset Filter Log">
                                        <i class="bi bi-arrow-counterclockwise text-sm"></i>
                                        <span>Reset Filter Log</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 overflow-hidden shadow-sm mb-3">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                                    <tr>
                                        <th style="width: 140px;">Waktu & User</th>
                                        <th>Nama Siswa & Rombel</th>
                                        <th style="width: 110px;">Aksi</th>
                                        <th>Rincian Perubahan</th>
                                        <th class="text-center" style="width: 130px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($globalHistories as $gh)
                                        <tr class="{{ $gh->is_reverted ? 'opacity-60' : '' }}">
                                            <td>
                                                <div class="fw-bold text-slate-800 dark:text-slate-200">
                                                    {{ $gh->created_at->format('d/m/Y H:i') }}
                                                </div>
                                                <div class="text-xs text-muted" title="{{ $gh->created_at }}">
                                                    {{ $gh->created_at->diffForHumans() }}
                                                </div>
                                                <div class="text-xs text-cyan-600 dark:text-cyan-400 mt-1 d-flex align-items-center gap-1">
                                                    <i class="bi bi-person-fill"></i>
                                                    <span>{{ $gh->user->name ?? 'Sistem' }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($gh->siswa)
                                                    <div class="fw-bold text-slate-800 dark:text-slate-200">
                                                        <a href="javascript:void(0)" wire:click="openHistory('{{ $gh->siswa_id }}')" class="text-decoration-none text-sky-600 hover:underline">
                                                            {{ $gh->siswa->nama }}
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-muted">
                                                        Rombel: {{ $gh->siswa->rombel->nama ?? '-' }}
                                                        @if ($gh->siswa->nisn) • NISN: {{ $gh->siswa->nisn }} @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted fst-italic">Siswa telah dihapus</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($gh->action === 'create')
                                                    <span class="badge bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">Tambah</span>
                                                @elseif ($gh->action === 'update')
                                                    <span class="badge bg-sky-500/15 text-sky-600 border border-sky-500/30">Pembaruan</span>
                                                @elseif ($gh->action === 'bulk_update')
                                                    <span class="badge bg-purple-500/15 text-purple-600 border border-purple-500/30">Massal</span>
                                                @elseif ($gh->action === 'rollback')
                                                    <span class="badge bg-amber-500/15 text-amber-600 border border-amber-500/30">Undo</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $gh->action }}</span>
                                                @endif

                                                @if ($gh->is_reverted)
                                                    <div class="mt-1">
                                                        <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 text-[10px]">
                                                            Di-undo
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-slate-700 dark:text-slate-300 mb-1">
                                                    {{ $gh->description ?? 'Perubahan data' }}
                                                </div>
                                                @if ($gh->old_values && $gh->new_values)
                                                    <div class="d-flex flex-column gap-1">
                                                        @foreach ($gh->new_values as $field => $newVal)
                                                            @php
                                                                $oldVal = $gh->old_values[$field] ?? null;
                                                                $fieldLabel = App\Models\SiswaHistory::formatFieldName($field);
                                                                $oldFormatted = App\Models\SiswaHistory::formatFieldValue($field, $oldVal);
                                                                $newFormatted = App\Models\SiswaHistory::formatFieldValue($field, $newVal);
                                                            @endphp
                                                            <div class="p-1 px-2 rounded bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-800 text-xs d-flex flex-wrap items-center gap-1.5">
                                                                <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                                <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 font-normal">{!! $oldFormatted !!}</span>
                                                                <i class="bi bi-arrow-right text-muted small"></i>
                                                                <span class="badge bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 font-normal">{!! $newFormatted !!}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($gh->action === 'create')
                                                    <span class="text-xs text-muted">Pembuatan data awal siswa</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    @if (!$gh->is_reverted && $gh->action !== 'create' && !empty($gh->old_values) && $gh->siswa)
                                                        <button type="button" 
                                                                wire:click="undoHistory({{ $gh->id }})"
                                                                wire:confirm="Yakin ingin membatalkan (undo) perubahan ini?"
                                                                class="btn btn-outline-warning btn-sm py-1 px-2 text-xs font-semibold"
                                                                title="Undo Perubahan Ini">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Undo
                                                        </button>
                                                    @elseif ($gh->is_reverted)
                                                        <span class="badge bg-secondary text-muted text-[10px]">Sudah di-undo</span>
                                                    @endif

                                                    @if ($gh->siswa)
                                                        <button type="button" 
                                                                wire:click="openHistory('{{ $gh->siswa_id }}')" 
                                                                class="btn btn-outline-info btn-sm py-1 px-2 text-xs font-semibold"
                                                                title="Lihat Riwayat Lengkap Siswa Ini">
                                                            <i class="bi bi-clock-history"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-5 text-center text-muted">
                                                <i class="bi bi-clock-history fs-2 text-muted d-block mb-2"></i>
                                                Belum ada riwayat perubahan data siswa
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 mb-3">
                            {{ $globalHistories->links() }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 overflow-hidden shadow-sm">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th style="width: 40px;" class="text-center py-3">
                                <div class="form-check d-flex justify-content-center m-0 p-0">
                                    <input class="form-check-input m-0" type="checkbox" wire:model.live="selectAll">
                                </div>
                            </th>
                            <th class="py-3">No</th>
                            <th class="py-3">Nama</th>
                            <th class="py-3">NISN</th>
                            <th class="py-3">Rombel</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Tag</th>
                            <th class="text-center py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswaList as $s)
                            <tr wire:key="row-{{ $s->id }}">
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center m-0 p-0">
                                        <input class="form-check-input m-0" type="checkbox" wire:model.live="checkedSiswa" value="{{ $s->id }}">
                                    </div>
                                </td>
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
                                        <button wire:click="show('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-info" title="Lihat Detail"><i class="bi bi-eye"></i></button>
                                        <button wire:click="edit('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-warning" title="Edit Data"><i
                                                class="bi bi-pencil"></i></button>
                                        <button wire:click="openHistory('{{ $s->id }}')"
                                            class="btn btn-sm btn-outline-secondary" title="Riwayat Perubahan"><i
                                                class="bi bi-clock-history"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center">
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
    @endif

    {{-- SPA FORM VIEW FOR SISWA --}}
    @if ($action === 'create' || $action === 'edit')
        <div class="glass-panel rounded-3xl p-6 md:p-8 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl" x-transition>
            {{-- Form Header & Quick Back --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 mb-6 border-b border-slate-200/80 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="resetForm" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-all shadow-xs" title="Kembali ke Daftar Siswa">
                        <i class="bi bi-arrow-left text-lg"></i>
                    </button>
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold font-display text-slate-800 dark:text-white m-0">
                            @if ($action === 'edit')
                                <i class="bi bi-pencil-square text-amber-500 me-1"></i> Edit Data Siswa: <span class="text-sky-600 dark:text-cyan-400">{{ $nama }}</span>
                            @else
                                <i class="bi bi-person-plus-fill text-sky-500 dark:text-cyan-400 me-1"></i> Formulir Tambah Siswa Baru
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                            Pastikan data pokok, identitas kependudukan, dan rombel terisi dengan benar.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <button type="button" wire:click="resetForm" class="btn btn-outline-secondary btn-sm rounded-xl px-4 py-2 text-xs font-semibold">
                        Batal
                    </button>
                    <button type="button" wire:click="{{ $action === 'edit' ? 'update' : 'store' }}" wire:loading.attr="disabled" class="btn-logo-gradient inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-bold shadow-md">
                        <span wire:loading.remove wire:target="{{ $action === 'edit' ? 'update' : 'store' }}">
                            <i class="bi bi-check2-circle text-sm"></i> {{ $action === 'edit' ? 'Simpan Perubahan' : 'Simpan Siswa' }}
                        </span>
                        <span wire:loading wire:target="{{ $action === 'edit' ? 'update' : 'store' }}" class="inline-flex items-center gap-2">
                            <span class="spinner-border spinner-border-sm" role="status"></span> Menyimpan...
                        </span>
                    </button>
                </div>
            </div>

            {{-- Form Body --}}
            <form wire:submit="{{ $action == 'edit' ? 'update' : 'store' }}">
                <div class="space-y-6">
                    {{-- SECTION 1: DATA POKOK --}}
                    <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 dark:text-cyan-400 flex items-center justify-center font-bold text-xs">
                                1
                            </div>
                            <h3 class="text-sm font-bold font-display text-slate-800 dark:text-slate-200 m-0">Data Pokok Siswa</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <input wire:model="nama" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Masukkan nama lengkap siswa" required>
                                @error('nama') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Panggilan</label>
                                <input wire:model="panggilan" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Panggilan akrab">
                                @error('panggilan') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Jenis Kelamin</label>
                                <div class="flex items-center gap-4 pt-2">
                                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        <input wire:model="jenis_kelamin" type="radio" value="L" class="form-check-input mt-0">
                                        <span>Laki-laki (L)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        <input wire:model="jenis_kelamin" type="radio" value="P" class="form-check-input mt-0">
                                        <span>Perempuan (P)</span>
                                    </label>
                                </div>
                                @error('jenis_kelamin') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">NISN</label>
                                <input wire:model="nisn" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="10 digit NISN">
                                @error('nisn') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">NIS</label>
                                <input wire:model="nis" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nomor Induk Siswa">
                                @error('nis') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Rombongan Belajar (Rombel) <span class="text-rose-500">*</span></label>
                                <select wire:model="form_rombel_id" class="form-select rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" required>
                                    <option value="">-- Pilih Rombel --</option>
                                    @foreach ($rombels as $r)
                                        <option value="{{ $r->id }}">{{ $r->nama }}</option>
                                    @endforeach
                                </select>
                                @error('form_rombel_id') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Status Keaktifan <span class="text-rose-500">*</span></label>
                                <select wire:model="form_status" class="form-select rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" required>
                                    <option value="">-- Pilih Status --</option>
                                    @foreach ($statusOptions as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('form_status') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Lembaga</label>
                                <select wire:model="lembaga_id" class="form-select rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100">
                                    <option value="">-- Pilih Lembaga --</option>
                                    @foreach (daftar_lembaga() as $id => $namaLembaga)
                                        <option value="{{ $id }}">{{ $namaLembaga }}</option>
                                    @endforeach
                                </select>
                                @error('lembaga_id') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: KELAHIRAN & KEPENDUDUKAN --}}
                    <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                                2
                            </div>
                            <h3 class="text-sm font-bold font-display text-slate-800 dark:text-slate-200 m-0">Data Kelahiran & Kependudukan</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tempat Lahir</label>
                                <input wire:model="tempat_lahir" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Kota/Kabupaten lahir">
                                @error('tempat_lahir') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tanggal Lahir</label>
                                <input type="date" wire:model="tanggal_lahir" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100">
                                @error('tanggal_lahir') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">NIK (Nomor Induk Kependudukan)</label>
                                <input wire:model="nik" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="16 digit NIK siswa">
                                @error('nik') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">No. Kartu Keluarga (KK)</label>
                                <input wire:model="no_kk" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nomor KK keluarga">
                                @error('no_kk') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">No. Akte Kelahiran</label>
                                <input wire:model="no_akte" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nomor registrasi akte lahir">
                                @error('no_akte') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 3: KONTAK & ORANG TUA --}}
                    <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                                3
                            </div>
                            <h3 class="text-sm font-bold font-display text-slate-800 dark:text-slate-200 m-0">Kontak & Data Orang Tua</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-12">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Alamat Lengkap Siswa</label>
                                <textarea wire:model="alamat" rows="2" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nama jalan, RT/RW, Dusun, Desa/Kelurahan, Kecamatan, dsb."></textarea>
                                @error('alamat') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nomor Telepon / WhatsApp</label>
                                <input wire:model="telepon" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Contoh: 08123456789">
                                @error('telepon') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Ayah Kandung</label>
                                <input wire:model="ayah" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nama lengkap ayah">
                                @error('ayah') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Ibu Kandung</label>
                                <input wire:model="ibu" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Nama lengkap ibu">
                                @error('ibu') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 4: FOTO & TAGS --}}
                    <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-xs">
                                4
                            </div>
                            <h3 class="text-sm font-bold font-display text-slate-800 dark:text-slate-200 m-0">Foto Profil & Tag Label</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Foto Siswa</label>
                                <input type="file" wire:model="foto" class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" accept="image/*">
                                <div wire:loading wire:target="foto" class="mt-1 text-xs text-sky-600 dark:text-cyan-400">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengunggah foto...
                                </div>
                                @error('foto') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                                @if ($foto && !is_string($foto))
                                    <div class="mt-3 flex items-center gap-3 p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                                        <img src="{{ $foto->temporaryUrl() }}" class="w-20 h-20 rounded-xl object-cover shadow-sm">
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 block mb-0.5">Foto Baru Dipilih</span>
                                            Pratinjau sebelum disimpan
                                        </div>
                                    </div>
                                @elseif ($existing_foto)
                                    <div class="mt-3 flex items-center gap-3 p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                                        <img src="{{ asset('storage/' . $existing_foto) }}" class="w-20 h-20 rounded-xl object-cover shadow-sm">
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-bold text-slate-700 dark:text-slate-300 block mb-0.5">Foto Saat Ini</span>
                                            Unggah file baru jika ingin mengganti
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tag Label Siswa</label>
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
                                }" class="relative">
                                    <div class="form-control rounded-xl dark:bg-slate-900 dark:border-slate-700 flex flex-wrap gap-1.5 items-center p-2 min-h-[46px]" :class="{ 'ring-2 ring-sky-500/30 border-sky-500': open }">
                                        <template x-for="(tag, index) in tags" :key="index">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-500/15 text-sky-700 dark:text-cyan-300 border border-sky-500/30">
                                                <span x-text="tag"></span>
                                                <button type="button" @click="removeTag(index)" class="hover:text-rose-500 transition-colors ml-1 font-bold text-sm leading-none" aria-label="Remove">&times;</button>
                                            </span>
                                        </template>
                                        <input type="text" x-model="text" @keydown.enter.prevent="addTag(text)"
                                                @keydown.comma.prevent="addTag(text)" @keydown.escape="open = false"
                                                @focus="open = true" @click.away="open = false"
                                                class="border-0 p-0 m-0 flex-grow bg-transparent text-xs text-slate-800 dark:text-slate-200 focus:ring-0 focus:outline-hidden"
                                                style="min-width: 120px;"
                                                placeholder="Ketik tag & Enter...">
                                    </div>

                                    <div x-show="open && suggestions.length > 0" x-cloak
                                         class="absolute z-20 mt-1.5 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl overflow-auto max-h-48 divide-y divide-slate-100 dark:divide-slate-800">
                                        <template x-for="suggestion in suggestions" :key="suggestion">
                                            <div @click="addTag(suggestion)"
                                                 class="px-3.5 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-sky-50 dark:hover:bg-slate-800 cursor-pointer transition-colors flex items-center justify-between">
                                                <span x-text="suggestion"></span>
                                                <i class="bi bi-plus text-sky-500"></i>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
                                        <i class="bi bi-info-circle"></i> Tekan <kbd class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-[10px]">Enter</kbd> atau <kbd class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-[10px]">,</kbd> untuk menambahkan tag
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bottom Action Buttons --}}
                <div class="flex items-center justify-between gap-3 mt-8 pt-5 border-t border-slate-200/80 dark:border-slate-800">
                    <button type="button" wire:click="resetForm" class="btn btn-outline-secondary rounded-xl px-5 py-2.5 text-xs font-semibold">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="resetForm" class="btn btn-secondary rounded-xl px-5 py-2.5 text-xs font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-logo-gradient inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs font-bold shadow-lg">
                            <span wire:loading.remove wire:target="{{ $action == 'edit' ? 'update' : 'store' }}">
                                <i class="bi bi-check2-circle text-base"></i> {{ $action == 'edit' ? 'Simpan Perubahan Siswa' : 'Simpan Siswa' }}
                            </span>
                            <span wire:loading wire:target="{{ $action == 'edit' ? 'update' : 'store' }}" class="inline-flex items-center gap-2">
                                <span class="spinner-border spinner-border-sm" role="status"></span> Menyimpan Data...
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    {{-- SPA DETAIL VIEW FOR SISWA --}}
    @elseif ($action === 'show')
        @php
            $sData = App\Models\Siswa::with(['rombel', 'tags', 'histories.user'])->find($showId);
        @endphp
        @if ($sData)
            <div class="glass-panel rounded-3xl p-6 md:p-8 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl" x-transition>
                {{-- Detail Top Bar --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-200/80 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="resetForm" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-all shadow-xs" title="Kembali ke Daftar Siswa">
                            <i class="bi bi-arrow-left text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-xl md:text-2xl font-bold font-display text-slate-800 dark:text-white m-0 flex items-center gap-2">
                                <i class="bi bi-person-vcard text-sky-500 dark:text-cyan-400"></i> Detail Profil Siswa
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                                Informasi lengkap dan riwayat aktivitas siswa
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" wire:click="edit('{{ $sData->id }}')" class="btn btn-outline-warning btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5">
                            <i class="bi bi-pencil-square"></i> Edit Siswa
                        </button>
                        <button type="button" wire:click="openHistory('{{ $sData->id }}')" class="btn btn-outline-info btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5">
                            <i class="bi bi-clock-history"></i> Riwayat Perubahan ({{ $sData->histories()->count() }})
                        </button>
                        <button type="button" wire:click="resetForm" class="btn btn-secondary btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </button>
                    </div>
                </div>

                {{-- Profile Hero Card --}}
                <div class="p-6 rounded-2xl bg-gradient-to-r from-sky-600/10 via-cyan-500/10 to-emerald-500/10 border border-sky-500/20 dark:border-cyan-500/30 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                    <div class="flex items-center gap-4">
                        @if ($sData->foto)
                            <img src="{{ asset('storage/' . $sData->foto) }}" class="w-20 h-20 rounded-2xl object-cover shadow-md border-2 border-white dark:border-slate-800">
                        @else
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-sky-500 to-cyan-400 text-white flex items-center justify-center font-bold text-2xl shadow-md border-2 border-white dark:border-slate-800">
                                {{ strtoupper(substr($sData->nama, 0, 2)) }}
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-xl font-bold font-display text-slate-800 dark:text-white m-0">{{ $sData->nama }}</h3>
                                @if ($sData->panggilan)
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium">({{ $sData->panggilan }})</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-3 flex-wrap">
                                <span>NISN: <strong class="text-slate-700 dark:text-slate-200">{{ $sData->nisn ?? '-' }}</strong></span>
                                <span>NIS: <strong class="text-slate-700 dark:text-slate-200">{{ $sData->nis ?? '-' }}</strong></span>
                                <span>JK: <strong class="text-slate-700 dark:text-slate-200">{{ $sData->jenis_kelamin == 'L' ? 'Laki-laki' : ($sData->jenis_kelamin == 'P' ? 'Perempuan' : '-') }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                <span class="badge bg-sky-500/15 text-sky-700 dark:text-cyan-300 border border-sky-500/30">
                                    <i class="bi bi-door-open me-1"></i> Rombel: {{ $sData->rombel->nama ?? '-' }}
                                </span>
                                @php
                                    $detailStatusColor = match ((int) $sData->status) {
                                        1 => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
                                        2 => 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
                                        3 => 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
                                        4 => 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30',
                                        5 => 'bg-neutral-800/15 text-neutral-700 dark:text-neutral-300 border-neutral-500/30',
                                        6 => 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
                                        default => 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30',
                                    };
                                @endphp
                                <span class="badge {{ $detailStatusColor }} border">
                                    <i class="bi bi-check-circle me-1"></i> {{ status_siswa($sData->status) }}
                                </span>
                                <span class="badge bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-500/30">
                                    <i class="bi bi-building me-1"></i> {{ nama_lembaga($sData->lembaga_id) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detail Sections Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Card 1: Data Kelahiran & Identitas --}}
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 flex items-center gap-2">
                            <i class="bi bi-card-text text-sky-500"></i> Data Kependudukan & Kelahiran
                        </h4>
                        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">NIK (KTP/KIA)</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->nik ?? '-' }}</dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">Tempat, Tanggal Lahir</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">
                                    {{ $sData->tempat_lahir ?? '-' }}, {{ $sData->tanggal_lahir ? date('d M Y', strtotime($sData->tanggal_lahir)) : '-' }}
                                </dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">No. Kartu Keluarga (KK)</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->no_kk ?? '-' }}</dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">No. Akte Kelahiran</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->no_akte ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Card 2: Kontak & Orang Tua --}}
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 flex items-center gap-2">
                            <i class="bi bi-people text-emerald-500"></i> Orang Tua & Kontak
                        </h4>
                        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">Nama Ayah</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->ayah ?? '-' }}</dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">Nama Ibu</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->ibu ?? '-' }}</dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">Nomor Telepon/WA</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->telepon ?? '-' }}</dd>
                            </div>
                            <div class="py-2.5 flex justify-between gap-4">
                                <dt class="text-slate-500">Alamat</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $sData->alamat ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Card 3: Tag Label --}}
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 shadow-xs md:col-span-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 flex items-center gap-2">
                            <i class="bi bi-tags text-purple-500"></i> Tag Label Terpasang
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($sData->tags as $t)
                                <span class="badge bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/30 px-3 py-1.5 rounded-lg text-xs">
                                    #{{ $t->nama }}
                                </span>
                            @empty
                                <span class="text-xs text-slate-400 italic">Belum ada tag untuk siswa ini.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Bottom Return Button --}}
                <div class="mt-8 pt-5 border-t border-slate-200/80 dark:border-slate-800 flex justify-between items-center">
                    <button type="button" wire:click="resetForm" class="btn btn-secondary rounded-xl px-5 py-2.5 text-xs font-semibold">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Siswa
                    </button>
                    <button type="button" wire:click="edit('{{ $sData->id }}')" class="btn btn-warning rounded-xl px-5 py-2.5 text-xs font-semibold">
                        <i class="bi bi-pencil-square me-1"></i> Edit Data Ini
                    </button>
                </div>
            </div>
        @endif
    @endif

    {{-- MODAL BULK UPDATE (TAILWIND / ALPINE) --}}
    @if ($showBulkUpdateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-transition>
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-cyan-500/30 shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="p-4 md:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <h5 class="font-bold flex items-center gap-2 mb-0 text-slate-900 dark:text-white">
                        <i class="bi bi-pencil-square text-sky-500"></i> Update Data secara Massal
                    </h5>
                    <button type="button" wire:click="closeBulkUpdate" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold p-1">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        Perubahan akan diterapkan pada <strong class="text-sky-600 dark:text-cyan-400 font-bold">{{ count($checkedSiswa) }}</strong> siswa yang dipilih.
                    </p>
                    <div class="mb-4">
                        <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Kelas (Rombel)</label>
                        <select wire:model="bulkRombelId" class="form-select rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($rombels as $r)
                                <option value="{{ $r->id }}">{{ $r->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Status Keaktifan</label>
                        <select wire:model="bulkStatus" class="form-select rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($statusOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tambah Tag</label>
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
                        }" class="relative">
                            <div class="form-control rounded-xl dark:bg-slate-800 dark:border-slate-700 flex flex-wrap gap-1.5 items-center p-2 min-h-[42px]" :class="{ 'ring-2 ring-sky-500/30 border-sky-500': open }">
                                <template x-for="(tag, index) in tags" :key="index">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-sky-500/15 text-sky-700 dark:text-cyan-300 border border-sky-500/30">
                                        <span x-text="tag"></span>
                                        <button type="button" @click="removeTag(index)" class="hover:text-rose-500 transition-colors ml-1 font-bold text-sm leading-none" aria-label="Remove">&times;</button>
                                    </span>
                                </template>
                                <input type="text" x-model="text" @keydown.enter.prevent="addTag(text)"
                                        @keydown.comma.prevent="addTag(text)" @keydown.escape="open = false"
                                        @focus="open = true" @click.away="open = false"
                                        class="border-0 p-0 m-0 flex-grow bg-transparent text-xs text-slate-800 dark:text-slate-200 focus:ring-0 focus:outline-hidden"
                                        style="min-width: 100px;"
                                        placeholder="Ketik tag...">
                            </div>
                            <div x-show="open && suggestions.length > 0" x-cloak
                                 class="absolute z-20 mt-1.5 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl overflow-auto max-h-40 divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="suggestion in suggestions" :key="suggestion">
                                    <div @click="addTag(suggestion)"
                                         class="px-3 py-1.5 text-xs text-slate-700 dark:text-slate-300 hover:bg-sky-50 dark:hover:bg-slate-800 cursor-pointer transition-colors flex items-center justify-between">
                                        <span x-text="suggestion"></span>
                                        <i class="bi bi-plus text-sky-500"></i>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <button type="button" wire:click="closeBulkUpdate" class="btn btn-secondary rounded-xl flex-1 py-2 text-xs font-semibold">
                            Batal
                        </button>
                        <button wire:click="bulkUpdate" class="btn-logo-gradient rounded-xl flex-1 py-2 text-xs font-bold shadow-md" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL BULK DELETE (TAILWIND / ALPINE) --}}
    @if ($showBulkDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-transition>
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-rose-500/30 shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 md:p-5 bg-gradient-to-r from-rose-600 to-red-700 text-white flex items-center justify-between">
                    <h5 class="font-bold flex items-center gap-2 mb-0 text-white text-sm">
                        <i class="bi bi-exclamation-triangle"></i> Konfirmasi Hapus Massal
                    </h5>
                    <button type="button" wire:click="cancelBulkDelete" class="text-white hover:text-rose-200 text-xl font-bold p-1">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Anda yakin ingin menghapus <strong class="text-rose-600 font-bold">{{ count($checkedSiswa) }}</strong> siswa yang dipilih?
                    </p>
                    <p class="text-rose-500 text-xs font-semibold flex items-center gap-1.5 mt-1 mb-4">
                        <i class="bi bi-info-circle"></i> Tindakan ini tidak dapat dibatalkan!
                    </p>

                    <div class="mb-4">
                        <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Ketik karakter berikut: <strong class="text-base text-rose-600 font-bold ml-1 tracking-widest select-none">{{ $randomConfirmString }}</strong>
                        </label>
                        <input type="text" wire:model="confirmText" class="form-control rounded-xl dark:bg-slate-800 dark:border-slate-700 font-mono text-center uppercase tracking-widest" placeholder="Ketik karakter..." autocomplete="off">
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="cancelBulkDelete" class="btn btn-secondary rounded-xl flex-1 py-2 text-xs font-semibold">
                            Batal
                        </button>
                        <button wire:click="bulkDelete" class="btn btn-danger rounded-xl flex-1 py-2 text-xs font-bold shadow-md" wire:loading.attr="disabled">
                            <i class="bi bi-trash me-1"></i> Hapus Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL RIWAYAT PERUBAHAN SISWA (TAILWIND / ALPINE) --}}
    @if ($historySiswaId && $historySiswa)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-transition>
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-cyan-500/30 shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-4 md:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h5 class="font-bold flex items-center gap-2 mb-0 text-slate-900 dark:text-white">
                            <i class="bi bi-clock-history text-cyan-500"></i>
                            Riwayat Perubahan Data Siswa
                        </h5>
                        <div class="text-xs text-muted mt-1">
                            <strong class="text-slate-800 dark:text-slate-200">{{ $historySiswa->nama }}</strong>
                            <span class="mx-1">•</span> Rombel: <span class="fw-semibold">{{ $historySiswa->rombel->nama ?? '-' }}</span>
                            <span class="mx-1">•</span> NISN: <span class="fw-semibold">{{ $historySiswa->nisn ?? '-' }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="closeHistory" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold p-1">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="p-4 md:p-6 overflow-y-auto flex-1">
                    @php
                        $histories = $historySiswa->histories;
                        $latestUndoable = $histories->where('is_reverted', false)->whereIn('action', ['update', 'bulk_update', 'rollback'])->whereNotNull('old_values')->first();
                    @endphp

                    {{-- TOP ACTION / SUMMARY BAR --}}
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-3 mb-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
                        <div class="d-flex align-items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-500 d-flex align-items-center justify-center font-bold">
                                <i class="bi bi-journal-text fs-5"></i>
                            </div>
                            <div>
                                <div class="text-xs text-muted">Total Riwayat Perubahan</div>
                                <div class="fw-bold text-sm text-slate-800 dark:text-slate-200">
                                    {{ $histories->count() }} aktivitas tercatat
                                </div>
                            </div>
                        </div>

                        @if ($latestUndoable)
                            <button type="button" 
                                    wire:click="undoLatestHistory('{{ $historySiswa->id }}')"
                                    wire:confirm="Yakin ingin membatalkan (undo) perubahan terakhir pada siswa ini?"
                                    class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1.5 fw-bold shadow-xs">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span>Undo Perubahan Terakhir</span>
                            </button>
                        @endif
                    </div>

                    {{-- TIMELINE / TABLE OF HISTORIES --}}
                    @if ($histories->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock fs-1 d-block mb-2 text-slate-300 dark:text-slate-600"></i>
                            <p class="mb-1 fw-bold">Belum ada riwayat perubahan yang tercatat</p>
                            <p class="small text-muted m-0">Setiap perubahan data siswa ke depan akan otomatis terekam secara kronologis di sini.</p>
                        </div>
                    @else
                        <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-slate-700/80">
                            <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-slate-600 dark:text-slate-300">
                                    <tr>
                                        <th style="width: 140px;">Waktu & User</th>
                                        <th style="width: 120px;">Aksi</th>
                                        <th>Rincian Perubahan (Sebelum <i class="bi bi-arrow-right"></i> Sesudah)</th>
                                        <th class="text-center" style="width: 110px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($histories as $h)
                                        <tr class="{{ $h->is_reverted ? 'opacity-60' : '' }}">
                                            <td>
                                                <div class="fw-bold text-slate-800 dark:text-slate-200">
                                                    {{ $h->created_at->format('d/m/Y H:i') }}
                                                </div>
                                                <div class="text-xs text-muted" title="{{ $h->created_at }}">
                                                    {{ $h->created_at->diffForHumans() }}
                                                </div>
                                                <div class="text-xs text-cyan-600 dark:text-cyan-400 mt-1 d-flex align-items-center gap-1">
                                                    <i class="bi bi-person-fill"></i>
                                                    <span>{{ $h->user->name ?? 'Sistem' }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($h->action === 'create')
                                                    <span class="badge bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">Tambah Data</span>
                                                @elseif ($h->action === 'update')
                                                    <span class="badge bg-sky-500/15 text-sky-600 border border-sky-500/30">Pembaruan</span>
                                                @elseif ($h->action === 'bulk_update')
                                                    <span class="badge bg-purple-500/15 text-purple-600 border border-purple-500/30">Massal</span>
                                                @elseif ($h->action === 'rollback')
                                                    <span class="badge bg-amber-500/15 text-amber-600 border border-amber-500/30">Undo/Rollback</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $h->action }}</span>
                                                @endif

                                                @if ($h->is_reverted)
                                                    <div class="mt-1">
                                                        <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 text-[10px]">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Sudah Di-undo
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-slate-700 dark:text-slate-300 mb-1">
                                                    {{ $h->description ?? 'Perubahan data' }}
                                                </div>

                                                @if ($h->old_values && $h->new_values)
                                                    <div class="d-flex flex-column gap-1 mt-1.5">
                                                        @foreach ($h->new_values as $field => $newVal)
                                                            @php
                                                                $oldVal = $h->old_values[$field] ?? null;
                                                                $fieldLabel = App\Models\SiswaHistory::formatFieldName($field);
                                                                $oldFormatted = App\Models\SiswaHistory::formatFieldValue($field, $oldVal);
                                                                $newFormatted = App\Models\SiswaHistory::formatFieldValue($field, $newVal);
                                                            @endphp
                                                            <div class="p-1.5 px-2 rounded-lg bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-800 text-xs d-flex flex-wrap items-center gap-1.5">
                                                                <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                                <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 font-normal">{!! $oldFormatted !!}</span>
                                                                <i class="bi bi-arrow-right text-muted small"></i>
                                                                <span class="badge bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 font-normal">{!! $newFormatted !!}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($h->action === 'create')
                                                    <span class="text-xs text-muted">Pembuatan entri awal data siswa</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if (!$h->is_reverted && $h->action !== 'create' && !empty($h->old_values))
                                                    <button type="button" 
                                                            wire:click="undoHistory({{ $h->id }})"
                                                            wire:confirm="Yakin ingin membatalkan perubahan ini dan mengembalikan nilai ke sebelum perubahan?"
                                                            class="btn btn-outline-warning btn-sm py-1 px-2 text-xs font-semibold rounded-lg"
                                                            title="Batalkan perubahan ini">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Undo
                                                    </button>
                                                @elseif ($h->is_reverted)
                                                    <span class="text-muted text-xs italic">
                                                        Di-undo {{ $h->reverted_at ? $h->reverted_at->diffForHumans() : '' }}
                                                    </span>
                                                @else
                                                    <span class="text-muted text-xs">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="p-3 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                    <button type="button" class="btn btn-secondary btn-sm rounded-xl px-4 py-2 text-xs font-semibold" wire:click="closeHistory">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
