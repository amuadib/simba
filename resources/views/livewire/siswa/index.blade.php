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
        $this->dispatch('show-modal-delete');
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
        $this->dispatch('close-modal', id: 'modalBulkDelete');
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
        $this->dispatch('show-modal-siswa');
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
        $this->dispatch('close-modal', id: 'modalSiswaForm');
        $this->dispatch('toast', message: 'Siswa berhasil ditambahkan', type: 'success');
    }

    public function show($id)
    {
        $this->editingId = null;
        $this->showId = $id;
        $this->action = 'show';
        $this->dispatch('show-modal-siswa');
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
        $this->dispatch('show-modal-siswa');
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
        $this->dispatch('close-modal', id: 'modalSiswaForm');
        $this->dispatch('toast', message: 'Siswa berhasil diperbarui', type: 'success');
    }


    public function resetForm()
    {
        $this->reset(['nama', 'panggilan', 'jenis_kelamin', 'nisn', 'form_rombel_id', 'form_status', 'selectedTags', 'action', 'editingId', 'showId', 'nik', 'nis', 'no_akte', 'no_kk', 'tempat_lahir', 'tanggal_lahir', 'lembaga_id', 'alamat', 'telepon', 'ayah', 'ibu', 'foto', 'existing_foto']);
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
        $this->dispatch('close-modal', id: 'modalBulkUpdate');
        $this->dispatch('toast', message: 'Data siswa berhasil diperbarui secara massal', type: 'success');
    }

    // --- RIWAYAT PERUBAHAN & UNDO ---

    public function openHistory($id)
    {
        $this->historySiswaId = $id;
        $this->dispatch('show-modal-history');
    }

    public function closeHistory()
    {
        $this->historySiswaId = null;
        $this->dispatch('close-modal', id: 'modalSiswaHistory');
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
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Data Siswa</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola data peserta didik, rombongan belajar & status keaktifan</p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

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
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" data-bs-toggle="modal"
                                data-bs-target="#modalBulkUpdate">
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

    
    {{-- MODAL SISWA FORM --}}
    <div wire:ignore.self class="modal fade" id="modalSiswaForm" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if($action == 'edit') <i class="bi bi-pencil-square me-2"></i> Edit Data Siswa
                        @elseif($action == 'create') <i class="bi bi-plus-circle me-2"></i> Tambah Data Siswa
                        @else <i class="bi bi-eye me-2"></i> Detail Siswa @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="resetForm"></button>
                </div>
                <div class="modal-body">
                    {{-- FORM AREA --}}
                    @if ($action == 'edit' || $action == 'create')
                            <div class="bg-primary bg-opacity-10 rounded border p-3">
                                
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
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">NIK</label>
                                        <input wire:model="nik" class="form-control" placeholder="NIK">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">NIS</label>
                                        <input wire:model="nis" class="form-control" placeholder="NIS">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">No Akte</label>
                                        <input wire:model="no_akte" class="form-control" placeholder="No Akte">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">No KK</label>
                                        <input wire:model="no_kk" class="form-control" placeholder="No KK">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tempat Lahir</label>
                                        <input wire:model="tempat_lahir" class="form-control" placeholder="Tempat Lahir">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tanggal Lahir</label>
                                        <input type="date" wire:model="tanggal_lahir" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Lembaga</label>
                                        <select wire:model="lembaga_id" class="form-select" required>
                                            <option value="">Pilih Lembaga</option>
                                            @foreach (daftar_lembaga() as $id => $namaLembaga)
                                                <option value="{{ $id }}">{{ $namaLembaga }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Telepon</label>
                                        <input wire:model="telepon" class="form-control" placeholder="Telepon">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold">Alamat</label>
                                        <input wire:model="alamat" class="form-control" placeholder="Alamat Lengkap">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Ayah</label>
                                        <input wire:model="ayah" class="form-control" placeholder="Nama Ayah">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Ibu</label>
                                        <input wire:model="ibu" class="form-control" placeholder="Nama Ibu">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Foto Siswa</label>
                                        <input type="file" wire:model="foto" class="form-control" accept="image/*">
                                        <div wire:loading wire:target="foto" class="mt-1 small text-muted">Mengunggah...</div>
                                        @error('foto') <span class="text-danger small">{{ $message }}</span> @enderror
                                        @if ($foto && !is_string($foto))
                                            <div class="mt-2">
                                                <img src="{{ $foto->temporaryUrl() }}" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        @elseif ($existing_foto)
                                            <div class="mt-2">
                                                <img src="{{ asset('storage/' . $existing_foto) }}" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        @endif
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
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" wire:click="resetForm">BATAL</button>
                                    </div>
                                </form>
                            </div>
                        @elseif($action == 'show')
                            <div class="bg-info rounded border bg-opacity-10 p-3">
                                
                                @php $sData = App\Models\Siswa::find($showId); @endphp
                                @if ($sData)
                                    <div class="row g-3">
                                        <div class="col-md-3"><strong>Nama</strong><br>{{ $sData->nama }}</div>
                                        <div class="col-md-2"><strong>Panggilan</strong><br>{{ $sData->panggilan ?? '-' }}</div>
                                        <div class="col-md-2"><strong>JK</strong><br>{{ $sData->jenis_kelamin }}</div>
                                        <div class="col-md-2"><strong>NISN</strong><br>{{ $sData->nisn ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Rombel</strong><br>{{ $sData->rombel->nama }}</div>
                                        <div class="col-md-3"><strong>NIK</strong><br>{{ $sData->nik ?? '-' }}</div>
                                        <div class="col-md-2"><strong>NIS</strong><br>{{ $sData->nis ?? '-' }}</div>
                                        <div class="col-md-2"><strong>No Akte</strong><br>{{ $sData->no_akte ?? '-' }}</div>
                                        <div class="col-md-2"><strong>No KK</strong><br>{{ $sData->no_kk ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Tempat Lahir</strong><br>{{ $sData->tempat_lahir ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Tanggal Lahir</strong><br>{{ $sData->tanggal_lahir ? date('d M Y', strtotime($sData->tanggal_lahir)) : '-' }}</div>
                                        <div class="col-md-2"><strong>Lembaga</strong><br>{{ nama_lembaga($sData->lembaga_id) }}</div>
                                        <div class="col-md-4"><strong>Alamat</strong><br>{{ $sData->alamat ?? '-' }}</div>
                                        <div class="col-md-2"><strong>Telepon</strong><br>{{ $sData->telepon ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Ayah</strong><br>{{ $sData->ayah ?? '-' }}</div>
                                        <div class="col-md-3"><strong>Ibu</strong><br>{{ $sData->ibu ?? '-' }}</div>
                                        <div class="col-md-12"><strong>Foto</strong><br>
                                            @if($sData->foto)
                                                <img src="{{ asset('storage/' . $sData->foto) }}" class="img-thumbnail mt-2" style="max-height: 150px;">
                                            @else
                                                -
                                            @endif
                                        </div>
                                        <div class="col-12 mt-3 d-flex gap-2">
                                            <button type="button" data-bs-dismiss="modal" wire:click="resetForm" class="btn btn-sm btn-secondary">Tutup</button>
                                            <button type="button" wire:click="openHistory('{{ $sData->id }}')" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-clock-history me-1"></i> Riwayat Perubahan ({{ $sData->histories()->count() }})
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
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
                    <h5 class="modal-title">Update Data secara Massal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Perubahan akan diterapkan pada <strong>{{ count($checkedSiswa) }}</strong> siswa yang dipilih.
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

    {{-- MODAL BULK DELETE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus Massal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus <strong>{{ count($checkedSiswa) }}</strong> siswa yang dipilih?</p>
                    <p class="text-danger small"><i class="bi bi-info-circle me-1"></i>Tindakan ini tidak dapat dibatalkan!</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Ketik karakter berikut untuk konfirmasi: <strong class="fs-5 ms-2 user-select-none">{{ $randomConfirmString }}</strong></label>
                        <input type="text" wire:model="confirmText" class="form-control" placeholder="Ketik karakter konfirmasi..." autocomplete="off">
                    </div>
                    
                    <button wire:click="bulkDelete" class="btn btn-danger w-100" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-1"></i> HAPUS DATA
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL RIWAYAT PERUBAHAN SISWA --}}
    <div wire:ignore.self class="modal fade" id="modalSiswaHistory" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <div>
                        <h5 class="modal-title font-bold flex items-center gap-2 mb-0">
                            <i class="bi bi-clock-history text-cyan-500"></i>
                            Riwayat Perubahan Data Siswa
                        </h5>
                        @if ($historySiswa)
                            <div class="text-xs text-muted mt-1">
                                <strong class="text-slate-800 dark:text-slate-200">{{ $historySiswa->nama }}</strong>
                                <span class="mx-1">•</span> Rombel: <span class="fw-semibold">{{ $historySiswa->rombel->nama ?? '-' }}</span>
                                <span class="mx-1">•</span> NISN: <span class="fw-semibold">{{ $historySiswa->nisn ?? '-' }}</span>
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="closeHistory"></button>
                </div>
                <div class="modal-body p-4">
                    @if ($historySiswa)
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
                    @endif
                </div>
                <div class="modal-footer border-top py-2.5">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" wire:click="closeHistory">Tutup</button>
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

            $wire.on('show-modal-delete', () => {
                new bootstrap.Modal(document.getElementById('modalBulkDelete')).show();
            });

            $wire.on('show-modal-siswa', () => {
                new bootstrap.Modal(document.getElementById('modalSiswaForm')).show();
            });

            $wire.on('show-modal-history', () => {
                new bootstrap.Modal(document.getElementById('modalSiswaHistory')).show();
            });
        </script>
    @endscript
