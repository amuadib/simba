@section('title', 'Data Pegawai')

<?php

use App\Models\Pegawai;
use App\Models\PegawaiHistory;
use App\Models\SkPenugasan;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PegawaiImport;
use App\Exports\PegawaiExport;
use App\Models\User;
use App\Models\Siswa;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new class extends \Livewire\Volt\Component {
    use WithPagination, WithFileUploads;

    // Filter & Search
    public $q = '';
    public $status = '';
    public $jenis_kelamin = '';
    public $lembaga_id = '';
    public $jabatan_id = '';

    // History State
    public $historyPegawaiId = null;
    public $historyActionFilter = '';
    public $historySearch = '';

    // Action State
    public $action = ''; // '', 'create', 'edit', 'show'
    public $editingId = null;
    public $showId = null;

    // Form Fields Pegawai
    public $niy, $nuptk, $nama, $gelar_depan, $gelar_belakang, $form_jenis_kelamin = 'l';
    public $nik, $tempat_lahir, $tanggal_lahir, $tmt, $no_hp, $alamat, $form_status = '1', $form_lembaga_id = 99;
    public $foto, $existing_foto;
    public $form_nomor_sk = '', $form_jabatan = '';

    // SK Penugasan Modal State & Form
    public $selectedPegawaiId = null;
    public $sk_id = null;
    public $sk_lembaga_id = 99;
    public $sk_nomor = '';
    public $sk_tanggal = '';
    public $sk_tmt_tugas = '';
    public $sk_tmt_selesai = '';
    public $sk_jabatan = '';
    public $sk_is_induk = false;
    public $sk_status = '1';
    public $sk_keterangan = '';
    public $sk_file = null;
    public $sk_existing_file = null;
    public $isEditingSk = false;

    // Checkbox Selection
    public $checkedPegawai = [];
    public $selectAll = false;
    public $randomConfirmString = '';
    public $confirmText = '';

    // Bulk Actions
    public $bulkStatus = '';
    public $bulkLembagaId = '';
    public $showBulkUpdateModal = false;
    public $showBulkDeleteModal = false;

    // Akun State
    public $showAccountModal = false;
    public $accountPegawaiId = null;
    public $account_type = 'new'; // 'new' or 'existing'
    public $account_name = '';
    public $account_email = '';
    public $account_role = 'guru';
    public $account_password = 'password';
    public $existing_user_id = '';

    // Import
    public $importFile;

    public $paginationTheme = 'bootstrap';

    public function updated($property)
    {
        if (in_array($property, ['q', 'status', 'jenis_kelamin', 'lembaga_id', 'jabatan_id'])) {
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
                ->when($this->lembaga_id, fn($q, $lid) => $q->where(function ($sub) use ($lid) {
                    $sub->where('lembaga_id', $lid)
                        ->orWhereHas('skPenugasan', fn($sk) => $sk->where('lembaga_id', $lid));
                }))
                ->when($this->jabatan_id, fn($q, $jid) => $q->whereHas('skPenugasan', fn($sk) => $sk->where('jabatan', $jid)))
                ->when($this->q, function ($q, $search) {
                    $matchedJabatanIds = collect(daftar_jabatan())
                        ->filter(fn($name) => stripos($name, $search) !== false)
                        ->keys()
                        ->toArray();

                    $q->where(function ($sub) use ($search, $matchedJabatanIds) {
                        $sub->where('nama', 'like', "%{$search}%")
                            ->orWhere('niy', 'like', "%{$search}%")
                            ->orWhere('nuptk', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%")
                            ->orWhereHas('skPenugasan', fn($sk) => $sk->where('nomor_sk', 'like', "%{$search}%")
                                ->when(!empty($matchedJabatanIds), fn($s) => $s->orWhereIn('jabatan', $matchedJabatanIds)));
                    });
                })
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

        SkPenugasan::$suppressHistory = true;
        $pegawais = Pegawai::with('skPenugasan')->whereIn('id', $this->checkedPegawai)->get();
        foreach ($pegawais as $p) {
            if ($p->foto && Storage::disk('public')->exists($p->foto)) {
                Storage::disk('public')->delete($p->foto);
            }
            foreach ($p->skPenugasan as $sk) {
                if ($sk->file_sk && Storage::disk('public')->exists($sk->file_sk)) {
                    Storage::disk('public')->delete($sk->file_sk);
                }
            }
            $p->delete();
        }
        SkPenugasan::$suppressHistory = false;

        $this->checkedPegawai = [];
        $this->selectAll = false;
        $this->showBulkDeleteModal = false;
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

    // --- CRUD ACTIONS PEGAWAI ---

    public function create()
    {
        $this->resetForm();
        $this->action = 'create';
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
            'form_nomor_sk' => 'nullable|string|max:255',
            'form_jabatan' => 'nullable|integer',
        ]);

        $niy = trim((string) $this->niy);
        if (empty($niy)) {
            $niy = Pegawai::generateNiy($this->tmt, $this->form_jenis_kelamin);
        }

        $fotoPath = $this->foto ? $this->foto->store('pegawai', 'public') : null;

        $pegawai = Pegawai::create([
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

        // Buat penugasan awal di lembaga induk
        if ($this->form_lembaga_id) {
            $pegawai->skPenugasan()->create([
                'lembaga_id' => $this->form_lembaga_id,
                'nomor_sk' => $this->form_nomor_sk ?: null,
                'tmt_tugas' => $this->tmt ?: null,
                'jabatan' => $this->form_jabatan ?: null,
                'is_induk' => true,
                'status' => '1',
            ]);
        }

        $this->resetForm();
        $this->dispatch('toast', message: 'Pegawai berhasil ditambahkan', type: 'success');
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

        $pegawai = Pegawai::with('skPenugasan')->findOrFail($id);
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

        $indukSk = $pegawai->skPenugasan->firstWhere('is_induk', true);
        $this->form_nomor_sk = $indukSk?->nomor_sk ?? '';
        $this->form_jabatan = $indukSk?->jabatan ?? '';
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
            'form_nomor_sk' => 'nullable|string|max:255',
            'form_jabatan' => 'nullable|integer',
        ]);

        $pegawai = Pegawai::findOrFail($this->editingId);

        $niy = trim((string) $this->niy);
        if (empty($niy)) {
            $niy = Pegawai::generateNiy($this->tmt, $this->form_jenis_kelamin);
        }

        $fotoPath = $pegawai->foto;
        if ($this->foto) {
            if ($pegawai->foto && Storage::disk('public')->exists($pegawai->foto)) {
                Storage::disk('public')->delete($pegawai->foto);
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

        // Pastikan ada sk_penugasan induk yang selaras
        $indukSk = $pegawai->skPenugasan()->where('is_induk', true)->first();
        if ($indukSk) {
            $indukSk->update([
                'lembaga_id' => $this->form_lembaga_id ?: 99,
                'nomor_sk' => $this->form_nomor_sk ?: null,
                'jabatan' => $this->form_jabatan ?: null,
            ]);
        } else {
            $pegawai->skPenugasan()->create([
                'lembaga_id' => $this->form_lembaga_id ?: 99,
                'nomor_sk' => $this->form_nomor_sk ?: null,
                'tmt_tugas' => $this->tmt ?: null,
                'jabatan' => $this->form_jabatan ?: null,
                'is_induk' => true,
                'status' => '1',
            ]);
        }

        $this->resetForm();
        $this->dispatch('toast', message: 'Pegawai berhasil diperbarui', type: 'success');
    }

    public function delete($id)
    {
        SkPenugasan::$suppressHistory = true;
        $pegawai = Pegawai::with('skPenugasan')->findOrFail($id);
        if ($pegawai->foto && Storage::disk('public')->exists($pegawai->foto)) {
            Storage::disk('public')->delete($pegawai->foto);
        }
        foreach ($pegawai->skPenugasan as $sk) {
            if ($sk->file_sk && Storage::disk('public')->exists($sk->file_sk)) {
                Storage::disk('public')->delete($sk->file_sk);
            }
        }
        $pegawai->delete();
        SkPenugasan::$suppressHistory = false;
        $this->dispatch('toast', message: 'Pegawai berhasil dihapus', type: 'success');
    }

    public function resetForm()
    {
        $this->reset([
            'nama', 'niy', 'nuptk', 'tmt', 'tempat_lahir', 'tanggal_lahir',
            'nik', 'alamat', 'no_hp', 'gelar_depan', 'gelar_belakang',
            'foto', 'existing_foto', 'action', 'editingId', 'showId',
            'form_nomor_sk', 'form_jabatan', 'selectedPegawaiId'
        ]);
        $this->form_jenis_kelamin = 'l';
        $this->form_lembaga_id = 99;
        $this->form_status = '1';
    }

    public function openBulkUpdate()
    {
        $this->showBulkUpdateModal = true;
    }

    public function closeBulkUpdate()
    {
        $this->showBulkUpdateModal = false;
        $this->bulkStatus = '';
        $this->bulkLembagaId = '';
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

        foreach ($this->checkedPegawai as $pId) {
            $p = Pegawai::find($pId);
            if ($p) {
                $p->historyAction = 'bulk_update';
                $p->historyDescription = 'Pembaruan data pegawai secara massal';
                $p->update($updateData);
            }
        }

        if (!empty($this->bulkLembagaId)) {
            $isNonAktif = !empty($this->bulkStatus) && in_array((int) $this->bulkStatus, [3, 4, 5, 6, 7]);
            $skStatus = $isNonAktif ? '0' : '1';
            foreach ($this->checkedPegawai as $pId) {
                $p = Pegawai::find($pId);
                if ($p) {
                    $existing = $p->skPenugasan()->where('lembaga_id', $this->bulkLembagaId)->first();
                    if ($existing) {
                        $p->skPenugasan()->where('id', '!=', $existing->id)->update(['is_induk' => false]);
                        $existing->update(['is_induk' => true, 'status' => $skStatus]);
                    } else {
                        $p->skPenugasan()->update(['is_induk' => false]);
                        $p->skPenugasan()->create([
                            'lembaga_id' => $this->bulkLembagaId,
                            'jabatan' => null,
                            'is_induk' => true,
                            'status' => $skStatus,
                        ]);
                    }
                }
            }
        }

        $this->reset(['bulkStatus', 'bulkLembagaId', 'checkedPegawai', 'selectAll']);
        $this->showBulkUpdateModal = false;
        $this->dispatch('toast', message: 'Data pegawai berhasil diperbarui secara massal', type: 'success');
    }

    // --- RIWAYAT PERUBAHAN & UNDO ---

    public function openHistory($id)
    {
        $this->historyPegawaiId = $id;
    }

    public function closeHistory()
    {
        $this->historyPegawaiId = null;
    }

    public function undoHistory($historyId)
    {
        $history = PegawaiHistory::findOrFail($historyId);
        $pegawai = Pegawai::findOrFail($history->pegawai_id);

        if ($history->is_reverted) {
            $this->dispatch('toast', message: 'Perubahan ini sudah pernah dibatalkan (undo) sebelumnya.', type: 'warning');
            return;
        }

        if ($history->action === 'create') {
            $this->dispatch('toast', message: 'Data penambahan awal pegawai tidak dapat di-undo.', type: 'error');
            return;
        }

        $success = $pegawai->rollbackHistory($history);
        if ($success) {
            $this->dispatch('toast', message: 'Perubahan data berhasil dibatalkan (undo).', type: 'success');
        } else {
            $this->dispatch('toast', message: 'Gagal meng-undo perubahan data.', type: 'error');
        }
    }

    public function undoLatestHistory($pegawaiId)
    {
        $latest = PegawaiHistory::where('pegawai_id', $pegawaiId)
            ->where('is_reverted', false)
            ->whereIn('action', ['update', 'bulk_update', 'rollback', 'sk_create', 'sk_update', 'sk_delete'])
            ->where(function ($q) {
                $q->whereNotNull('old_values')->orWhere('action', 'sk_create');
            })
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            $this->dispatch('toast', message: 'Tidak ada perubahan yang dapat di-undo untuk pegawai ini.', type: 'warning');
            return;
        }

        $this->undoHistory($latest->id);
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

    // --- MANAJEMEN SK PENUGASAN ---

    public function kelolaSk($pegawaiId)
    {
        $this->selectedPegawaiId = $pegawaiId;
        $this->action = 'sk';
        $this->resetSkForm();
    }

    public function tutupSk()
    {
        $this->action = '';
        $this->selectedPegawaiId = null;
        $this->resetSkForm();
    }

    public function resetSkForm()
    {
        $this->reset([
            'sk_id', 'sk_nomor', 'sk_tanggal', 'sk_tmt_tugas', 'sk_tmt_selesai',
            'sk_jabatan', 'sk_keterangan', 'sk_file', 'sk_existing_file', 'isEditingSk'
        ]);
        $this->sk_lembaga_id = 99;
        $this->sk_status = '1';
        $this->sk_is_induk = false;
    }

    public function editSk($skId)
    {
        $sk = SkPenugasan::findOrFail($skId);
        $this->sk_id = $sk->id;
        $this->sk_lembaga_id = $sk->lembaga_id;
        $this->sk_nomor = $sk->nomor_sk ?? '';
        $this->sk_tanggal = $sk->tanggal_sk ? $sk->tanggal_sk->format('Y-m-d') : '';
        $this->sk_tmt_tugas = $sk->tmt_tugas ? $sk->tmt_tugas->format('Y-m-d') : '';
        $this->sk_tmt_selesai = $sk->tmt_selesai ? $sk->tmt_selesai->format('Y-m-d') : '';
        $this->sk_jabatan = $sk->jabatan ?? '';
        $this->sk_is_induk = (bool) $sk->is_induk;
        $this->sk_status = (string) $sk->status;
        $this->sk_keterangan = $sk->keterangan ?? '';
        $this->sk_existing_file = $sk->file_sk;
        $this->sk_file = null;
        $this->isEditingSk = true;
    }

    public function cancelEditSk()
    {
        $this->resetSkForm();
    }

    public function saveSk()
    {
        $this->validate([
            'sk_lembaga_id' => 'required',
            'sk_nomor' => 'nullable|string|max:255',
            'sk_tanggal' => 'nullable|date',
            'sk_tmt_tugas' => 'nullable|date',
            'sk_tmt_selesai' => 'nullable|date',
            'sk_jabatan' => 'nullable|integer',
            'sk_status' => 'required',
            'sk_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'sk_keterangan' => 'nullable|string|max:500',
        ]);

        $pegawai = Pegawai::findOrFail($this->selectedPegawaiId);

        $filePath = $this->sk_existing_file;
        if ($this->sk_file) {
            if ($this->sk_existing_file && Storage::disk('public')->exists($this->sk_existing_file)) {
                Storage::disk('public')->delete($this->sk_existing_file);
            }
            $filePath = $this->sk_file->store('sk_penugasan', 'public');
        }

        $existingCount = $pegawai->skPenugasan()->count();
        $isInduk = $this->sk_is_induk || $existingCount === 0;

        if ($isInduk) {
            foreach ($pegawai->skPenugasan()->where('id', '!=', $this->sk_id)->where('is_induk', true)->get() as $oldInduk) {
                $oldInduk->update(['is_induk' => false]);
            }
            $pegawai->update(['lembaga_id' => $this->sk_lembaga_id]);
        }

        if ($this->isEditingSk && $this->sk_id) {
            $sk = SkPenugasan::findOrFail($this->sk_id);
            $sk->update([
                'lembaga_id' => $this->sk_lembaga_id,
                'nomor_sk' => $this->sk_nomor ?: null,
                'tanggal_sk' => $this->sk_tanggal ?: null,
                'tmt_tugas' => $this->sk_tmt_tugas ?: null,
                'tmt_selesai' => $this->sk_tmt_selesai ?: null,
                'jabatan' => $this->sk_jabatan ?: null,
                'is_induk' => $isInduk,
                'status' => (string) $this->sk_status,
                'file_sk' => $filePath,
                'keterangan' => $this->sk_keterangan ?: null,
            ]);
            $message = 'SK Penugasan berhasil diperbarui';
        } else {
            $pegawai->skPenugasan()->create([
                'lembaga_id' => $this->sk_lembaga_id,
                'nomor_sk' => $this->sk_nomor ?: null,
                'tanggal_sk' => $this->sk_tanggal ?: null,
                'tmt_tugas' => $this->sk_tmt_tugas ?: null,
                'tmt_selesai' => $this->sk_tmt_selesai ?: null,
                'jabatan' => $this->sk_jabatan ?: null,
                'is_induk' => $isInduk,
                'status' => (string) $this->sk_status,
                'file_sk' => $filePath,
                'keterangan' => $this->sk_keterangan ?: null,
            ]);
            $message = 'SK Penugasan berhasil ditambahkan';
        }

        $this->resetSkForm();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function deleteSk($skId)
    {
        $sk = SkPenugasan::findOrFail($skId);
        $pegawaiId = $sk->pegawai_id;
        $wasInduk = $sk->is_induk;

        if ($sk->file_sk && Storage::disk('public')->exists($sk->file_sk)) {
            Storage::disk('public')->delete($sk->file_sk);
        }
        $sk->delete();

        if ($wasInduk) {
            $another = SkPenugasan::where('pegawai_id', $pegawaiId)->first();
            if ($another) {
                $another->update(['is_induk' => true]);
                Pegawai::where('id', $pegawaiId)->update(['lembaga_id' => $another->lembaga_id]);
            }
        }

        if ($this->sk_id === $skId) {
            $this->resetSkForm();
        }

        $this->dispatch('toast', message: 'SK Penugasan berhasil dihapus', type: 'success');
    }

    public function setIndukSk($skId)
    {
        $sk = SkPenugasan::findOrFail($skId);
        $pegawai = Pegawai::findOrFail($sk->pegawai_id);

        foreach ($pegawai->skPenugasan()->where('is_induk', true)->get() as $oldInduk) {
            if ($oldInduk->id !== $sk->id) {
                $oldInduk->update(['is_induk' => false]);
            }
        }

        $sk->update(['is_induk' => true]);
        $pegawai->update(['lembaga_id' => $sk->lembaga_id]);

        $this->dispatch('toast', message: 'Lembaga induk berhasil diatur ke ' . nama_lembaga($sk->lembaga_id), type: 'success');
    }

    // --- MANAJEMEN AKUN PEGAWAI ---

    public function openBuatAkun($pegawaiId)
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $this->dispatch('toast', message: 'Hanya Admin yang dapat membuat atau menyambungkan akun.', type: 'error');
            return;
        }

        $pegawai = Pegawai::with('user')->findOrFail($pegawaiId);
        if ($pegawai->user_id) {
            $this->dispatch('toast', message: 'Pegawai ini sudah memiliki akun (' . $pegawai->user->email . ').', type: 'warning');
            return;
        }

        $this->accountPegawaiId = $pegawai->id;
        $this->account_type = 'new';
        $this->account_name = $pegawai->nama;

        // Buat saran email berdasarkan NIY atau nama
        $cleanBase = $pegawai->niy ?: Str::slug($pegawai->nama, '');
        $suggestedEmail = strtolower($cleanBase) . '@simba.id';
        $counter = 1;
        while (User::where('email', $suggestedEmail)->exists()) {
            $suggestedEmail = strtolower($cleanBase) . $counter . '@simba.id';
            $counter++;
        }

        $this->account_email = $suggestedEmail;
        $this->account_role = 'guru';
        $this->account_password = 'password';
        $this->existing_user_id = '';
        $this->showAccountModal = true;
    }

    public function closeAccountModal()
    {
        $this->showAccountModal = false;
        $this->accountPegawaiId = null;
        $this->reset(['account_name', 'account_email', 'account_role', 'account_password', 'existing_user_id']);
    }

    public function saveAkun()
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $this->dispatch('toast', message: 'Akses ditolak.', type: 'error');
            return;
        }

        $pegawai = Pegawai::findOrFail($this->accountPegawaiId);

        if ($this->account_type === 'existing') {
            $this->validate([
                'existing_user_id' => 'required|exists:users,id',
            ], [
                'existing_user_id.required' => 'Pilih akun yang ingin disambungkan.',
            ]);

            $alreadyLinked = Pegawai::where('user_id', $this->existing_user_id)->where('id', '!=', $pegawai->id)->exists()
                || Siswa::where('user_id', $this->existing_user_id)->exists();

            if ($alreadyLinked) {
                $this->dispatch('toast', message: 'Akun ini sudah terhubung dengan pegawai atau siswa lain.', type: 'error');
                return;
            }

            $pegawai->update(['user_id' => $this->existing_user_id]);
            $this->dispatch('toast', message: 'Akun berhasil disambungkan dengan pegawai!', type: 'success');
        } else {
            $this->validate([
                'account_name' => 'required|string|max:255',
                'account_email' => 'required|email|unique:users,email',
                'account_role' => 'required|in:admin,guru',
                'account_password' => 'required|min:6',
            ], [
                'account_email.unique' => 'Email ini sudah digunakan oleh akun lain.',
                'account_password.min' => 'Password minimal 6 karakter.',
            ]);

            $user = User::create([
                'name' => $this->account_name,
                'email' => $this->account_email,
                'role' => $this->account_role,
                'password' => Hash::make($this->account_password),
            ]);

            $pegawai->update(['user_id' => $user->id]);
            $this->dispatch('toast', message: 'Akun baru berhasil dibuat dan disambungkan ke pegawai!', type: 'success');
        }

        $this->closeAccountModal();
    }

    public function putusAkun($pegawaiId)
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $this->dispatch('toast', message: 'Akses ditolak.', type: 'error');
            return;
        }

        $pegawai = Pegawai::findOrFail($pegawaiId);
        $pegawai->update(['user_id' => null]);
        $this->dispatch('toast', message: 'Sambungan akun pegawai berhasil diputus.', type: 'success');
    }

    public function with()
    {
        $pegawai = Pegawai::query()
            ->with(['skPenugasan', 'user'])
            ->orderBy('niy')
            ->when($this->status, fn($q, $s) => $q->where('status', $s))
            ->when($this->jenis_kelamin, fn($q, $jk) => $q->where('jenis_kelamin', strtolower($jk)))
            ->when($this->lembaga_id, fn($q, $lid) => $q->where(function ($sub) use ($lid) {
                $sub->where('lembaga_id', $lid)
                    ->orWhereHas('skPenugasan', fn($sk) => $sk->where('lembaga_id', $lid));
            }))
            ->when($this->jabatan_id, fn($q, $jid) => $q->whereHas('skPenugasan', fn($sk) => $sk->where('jabatan', $jid)))
            ->when($this->q, function ($q, $search) {
                $matchedJabatanIds = collect(daftar_jabatan())
                    ->filter(fn($name) => stripos($name, $search) !== false)
                    ->keys()
                    ->toArray();

                $q->where(function ($sub) use ($search, $matchedJabatanIds) {
                    $sub->where('nama', 'like', "%{$search}%")
                        ->orWhere('niy', 'like', "%{$search}%")
                        ->orWhere('nuptk', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('no_hp', 'like', "%{$search}%")
                        ->orWhereHas('skPenugasan', fn($sk) => $sk->where('nomor_sk', 'like', "%{$search}%")
                            ->when(!empty($matchedJabatanIds), fn($s) => $s->orWhereIn('jabatan', $matchedJabatanIds)));
                });
            })
            ->paginate(25);

        $selectedPegawai = $this->selectedPegawaiId
            ? Pegawai::with(['skPenugasan', 'user'])->find($this->selectedPegawaiId)
            : null;

        $counts = [
            'total' => Pegawai::count(),
            'aktif' => Pegawai::where('status', '1')->count(),
            'laki' => Pegawai::where('jenis_kelamin', 'l')->count(),
            'perempuan' => Pegawai::where('jenis_kelamin', 'p')->count(),
        ];

        return [
            'pegawaiList' => $pegawai,
            'selectedPegawai' => $selectedPegawai,
            'counts' => $counts,
            'daftarLembaga' => daftar_lembaga(),
            'daftarJabatan' => daftar_jabatan(),
            'statusOptions' => config('local.status_pegawai', [
                1 => 'Aktif',
                2 => 'Cuti',
                3 => 'Non-Aktif',
                4 => 'Mutasi',
                5 => 'Pensiun',
                6 => 'Keluar',
            ]),
            'availableUsers' => User::whereDoesntHave('pegawai')->whereDoesntHave('siswa')->orderBy('name')->get(),
            'historyPegawai' => $this->historyPegawaiId ? Pegawai::with(['histories.user', 'histories.reverter'])->find($this->historyPegawaiId) : null,
            'globalHistories' => PegawaiHistory::with(['pegawai', 'user', 'reverter'])
                ->when($this->historyActionFilter, fn($q, $a) => $q->where('action', $a))
                ->when($this->historySearch, fn($q, $s) => $q->whereHas('pegawai', fn($pq) => $pq->where('nama', 'like', "%{$s}%")->orWhere('niy', 'like', "%{$s}%")->orWhere('nik', 'like', "%{$s}%")))
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'history_page'),
        ];
    }
};

?>

<div>
    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            @if ($action === 'create')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Tambah Data Pegawai</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Isi data tenaga pendidik atau kependidikan baru</p>
            @elseif ($action === 'edit')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Edit Data Pegawai</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Perbarui informasi data pegawai {{ $nama }}</p>
            @elseif ($action === 'show')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Detail Data Pegawai</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Informasi lengkap dan riwayat penugasan pegawai</p>
            @elseif ($action === 'sk')
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Kelola SK Penugasan Lembaga</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Atur SK penugasan, lembaga induk & masa tugas</p>
            @else
                <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Data Pegawai</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola data tenaga pendidik, kependidikan & riwayat SK penugasan lembaga</p>
            @endif
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    @if ($action === '')
    {{-- STATS CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
        {{-- Total Pegawai --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-display">Total Pegawai</div>
                    <div class="text-2xl font-extrabold text-sky-600 dark:text-cyan-400 font-display mt-0.5">{{ $counts['total'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-sky-500 to-cyan-500 text-white flex items-center justify-center text-xl shadow-md shadow-sky-500/30">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>

        {{-- Pegawai Aktif --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-emerald-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-display">Pegawai Aktif</div>
                    <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-display mt-0.5">{{ $counts['aktif'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-emerald-500 to-green-500 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-500/30">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
        </div>

        {{-- Laki-laki --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 font-display">Laki-laki</div>
                    <div class="text-2xl font-extrabold text-cyan-600 dark:text-cyan-400 font-display mt-0.5">{{ $counts['laki'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-cyan-500 to-sky-400 text-white flex items-center justify-center text-xl shadow-md shadow-cyan-500/30">
                    <i class="bi bi-gender-male"></i>
                </div>
            </div>
        </div>

        {{-- Perempuan --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-pink-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-pink-500 font-display">Perempuan</div>
                    <div class="text-2xl font-extrabold text-pink-500 font-display mt-0.5">{{ $counts['perempuan'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-pink-500 to-rose-400 text-white flex items-center justify-center text-xl shadow-md shadow-pink-500/30">
                    <i class="bi bi-gender-female"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CARD CONTAINER --}}
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
                            <span>Filter & Pencarian</span>
                        </button>
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'import' 
                                    ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' 
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60'"
                                @click="activeTab = 'import'" type="button">
                            <i class="bi bi-file-earmark-arrow-up-fill text-emerald-500"></i>
                            <span>Import Excel</span>
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

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        @if (count($checkedPegawai) > 0)
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="openBulkUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Update Status ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="prepareBulkDelete">
                                <i class="bi bi-trash me-1"></i> Hapus ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="bulkExport">
                                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ({{ count($checkedPegawai) }})
                            </button>
                        @endif

                        <button type="button" wire:click="exportAll" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-slate-750 hover:text-emerald-700 dark:hover:text-emerald-300 border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-500/40 text-xs font-bold transition-all shadow-xs">
                            <i class="bi bi-file-earmark-excel-fill text-emerald-600 dark:text-emerald-400 text-sm"></i>
                            <span>Ekspor Semua</span>
                        </button>

                        <button wire:click="create" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                            <i class="bi bi-plus-circle text-sm"></i> Tambah Pegawai
                        </button>
                    </div>
                </div>

                <div class="tab-content mb-4">
                    {{-- TAB FILTER --}}
                    <div x-show="activeTab === 'filter'">
                        <div class="p-3.5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                            <form wire:submit.prevent class="row g-2.5 items-center">
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm shadow-xs rounded-xl overflow-hidden">
                                        <span class="input-group-text border-end-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-sky-500 ps-3">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="q" class="form-control form-control-sm border-start-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 text-xs py-2" placeholder="Cari nama, NIY, NIK...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select wire:model.live="lembaga_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Lembaga --</option>
                                        @foreach ($daftarLembaga as $lid => $lname)
                                            <option value="{{ $lid }}">{{ $lname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="jabatan_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Jabatan --</option>
                                        @foreach ($daftarJabatan as $jid => $jname)
                                            <option value="{{ $jid }}">{{ $jname }}</option>
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
                                <div class="col-md-1">
                                    <select wire:model.live="jenis_kelamin" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- JK --</option>
                                        <option value="l">L</option>
                                        <option value="p">P</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" wire:click="resetPage" class="w-100 py-2 px-2.5 rounded-xl bg-white hover:bg-rose-50 dark:bg-slate-900 dark:hover:bg-rose-950/40 text-slate-600 hover:text-rose-600 dark:text-slate-300 dark:hover:text-rose-400 border border-slate-200 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-xs" title="Reset Filter">
                                        <i class="bi bi-arrow-counterclockwise text-sm"></i>
                                        <span class="hidden xl:inline">Reset</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- TAB IMPORT --}}
                    <div x-show="activeTab === 'import'" style="display: none;">
                        <div class="p-4 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                            <form wire:submit="import" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <input type="file" wire:model="importFile" class="form-control form-control-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" accept=".xlsx,.xls,.csv" required>
                                    @error('importFile')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-warning btn-sm w-100 rounded-xl fw-bold py-2 shadow-xs" wire:loading.attr="disabled">
                                        <i class="bi bi-upload me-1"></i> IMPORT EXCEL
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="exportTemplate" class="btn btn-outline-primary btn-sm w-100 rounded-xl fw-bold py-2 shadow-xs" wire:loading.attr="disabled">
                                        <i class="bi bi-download me-1"></i> UNDUH TEMPLATE
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- TAB RIWAYAT PEGAWAI GLOBAL --}}
                    <div x-show="activeTab === 'history'" style="display: none;">
                        <div class="p-3.5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-3">
                            <div class="row g-2.5 items-center">
                                <div class="col-md-5">
                                    <div class="input-group input-group-sm shadow-xs rounded-xl overflow-hidden">
                                        <span class="input-group-text border-end-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-cyan-500 ps-3">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="historySearch"
                                            class="form-control form-control-sm border-start-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 text-xs py-2" placeholder="Cari nama pegawai, NIY, NIK...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select wire:model.live="historyActionFilter" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl text-xs py-2 shadow-xs font-medium">
                                        <option value="">-- Semua Jenis Aksi --</option>
                                        <option value="update">Pembaruan Pegawai</option>
                                        <option value="sk_create">Tambah SK Penugasan</option>
                                        <option value="sk_update">Ubah SK Penugasan</option>
                                        <option value="sk_delete">Hapus SK Penugasan</option>
                                        <option value="bulk_update">Pembaruan Massal</option>
                                        <option value="rollback">Undo / Rollback</option>
                                        <option value="create">Penambahan Pegawai Baru</option>
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
                                        <th>Nama Pegawai & Lembaga</th>
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
                                                @if ($gh->pegawai)
                                                    <div class="fw-bold text-slate-800 dark:text-slate-200">
                                                        <a href="javascript:void(0)" wire:click="openHistory('{{ $gh->pegawai_id }}')" class="text-decoration-none text-sky-600 hover:underline">
                                                            {!! setNamaPegawai($gh->pegawai->nama, $gh->pegawai->gelar_depan, $gh->pegawai->gelar_belakang) !!}
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-muted">
                                                        {{ nama_lembaga($gh->pegawai->lembaga_id) }}
                                                        @if ($gh->pegawai->niy) • NIY: {{ $gh->pegawai->niy }} @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted fst-italic">Pegawai telah dihapus</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($gh->action === 'create')
                                                    <span class="badge bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">Tambah Pegawai</span>
                                                @elseif ($gh->action === 'update')
                                                    <span class="badge bg-sky-500/15 text-sky-600 border border-sky-500/30">Pembaruan</span>
                                                @elseif ($gh->action === 'bulk_update')
                                                    <span class="badge bg-purple-500/15 text-purple-600 border border-purple-500/30">Massal</span>
                                                @elseif ($gh->action === 'rollback')
                                                    <span class="badge bg-amber-500/15 text-amber-600 border border-amber-500/30">Undo</span>
                                                @elseif ($gh->action === 'sk_create')
                                                    <span class="badge bg-teal-500/15 text-teal-600 border border-teal-500/30">Tambah SK</span>
                                                @elseif ($gh->action === 'sk_update')
                                                    <span class="badge bg-blue-500/15 text-blue-600 border border-blue-500/30">Ubah SK</span>
                                                @elseif ($gh->action === 'sk_delete')
                                                    <span class="badge bg-rose-500/15 text-rose-600 border border-rose-500/30">Hapus SK</span>
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
                                                            @if (in_array($field, ['sk_id', 'id', 'pegawai_id'])) @continue @endif
                                                            @php
                                                                $oldVal = $gh->old_values[$field] ?? null;
                                                                $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                                $oldFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $oldVal);
                                                                $newFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $newVal);
                                                            @endphp
                                                            <div class="p-1 px-2 rounded bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-800 text-xs d-flex flex-wrap items-center gap-1.5">
                                                                <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                                <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 font-normal">{!! $oldFormatted !!}</span>
                                                                <i class="bi bi-arrow-right text-muted small"></i>
                                                                <span class="badge bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 font-normal">{!! $newFormatted !!}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($gh->action === 'sk_delete' && $gh->old_values)
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="text-xs text-rose-500 font-semibold mb-0.5">Data SK yang dihapus:</span>
                                                        @foreach ($gh->old_values as $field => $oldVal)
                                                            @if (in_array($field, ['sk_id', 'id', 'pegawai_id']) || $oldVal === null || $oldVal === '') @continue @endif
                                                            @php
                                                                $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                                $oldFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $oldVal);
                                                            @endphp
                                                            <div class="p-1 px-2 rounded bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-800 text-xs d-flex flex-wrap items-center gap-1.5">
                                                                <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                                <span class="badge bg-rose-500/10 text-rose-600 border border-rose-500/20 font-normal">{!! $oldFormatted !!}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($gh->action === 'sk_create' && $gh->new_values)
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="text-xs text-teal-600 dark:text-teal-400 font-semibold mb-0.5">Rincian SK baru:</span>
                                                        @foreach ($gh->new_values as $field => $newVal)
                                                            @if (in_array($field, ['sk_id', 'id', 'pegawai_id']) || $newVal === null || $newVal === '') @continue @endif
                                                            @php
                                                                $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                                $newFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $newVal);
                                                            @endphp
                                                            <div class="p-1 px-2 rounded bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-800 text-xs d-flex flex-wrap items-center gap-1.5">
                                                                <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                                <span class="badge bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 font-normal">{!! $newFormatted !!}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($gh->action === 'create')
                                                    <span class="text-xs text-muted">Pembuatan data awal pegawai</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    @if (!$gh->is_reverted && $gh->action !== 'create' && ($gh->old_values || $gh->action === 'sk_create') && $gh->pegawai)
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

                                                    @if ($gh->pegawai)
                                                        <button type="button" 
                                                                wire:click="openHistory('{{ $gh->pegawai_id }}')" 
                                                                class="btn btn-outline-info btn-sm py-1 px-2 text-xs font-semibold"
                                                                title="Lihat Riwayat Lengkap Pegawai Ini">
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
                                                Belum ada riwayat perubahan data pegawai
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
            <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="table table-hover align-middle mb-0" style="min-width: 820px;">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th style="width: 40px;" class="text-center py-3">
                                <div class="form-check d-flex justify-content-center m-0 p-0">
                                    <input class="form-check-input m-0" type="checkbox" wire:model.live="selectAll">
                                </div>
                            </th>
                            <th style="width: 50px;" class="py-3">No</th>
                            <th class="py-3">NIY</th>
                            <th class="py-3">Nama Pegawai</th>
                            <th class="text-center py-3">JK</th>
                            <th class="py-3">Lembaga Bertugas (SK)</th>
                            <th class="py-3">TMT</th>
                            <th class="py-3">Kontak</th>
                            <th class="py-3">Status</th>
                            <th class="text-center py-3" style="width: 155px;">Aksi</th>
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
                                        @if ($p->foto && Storage::disk('public')->exists($p->foto))
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
                                            @if ($p->user)
                                                <div class="mt-0.5">
                                                    <span class="badge bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" style="font-size: 0.68rem;" title="Akun: {{ $p->user->email }} ({{ strtoupper($p->user->role) }})">
                                                        <i class="bi bi-person-check me-1"></i>{{ $p->user->email }}
                                                    </span>
                                                </div>
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
                                    @php
                                        $activeSks = $p->skPenugasan->where('status', '1');
                                    @endphp
                                    @if ($activeSks->isNotEmpty())
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            @foreach ($activeSks as $sk)
                                                <span class="badge {{ $sk->is_induk ? 'bg-primary' : 'bg-info text-dark' }} d-inline-flex align-items-center gap-1 shadow-sm"
                                                      title="{{ $sk->is_induk ? '[Lembaga Induk] ' : '[Tugas Tambahan] ' }}{{ nama_lembaga($sk->lembaga_id) }}{{ $sk->jabatan ? ' - ' . $sk->nama_jabatan : '' }}{{ $sk->nomor_sk ? ' (SK: ' . $sk->nomor_sk . ')' : '' }}"
                                                      style="font-size: 0.75rem; cursor: pointer;"
                                                      wire:click="kelolaSk('{{ $p->id }}')">
                                                    @if ($sk->is_induk)
                                                        <i class="bi bi-star-fill text-warning" style="font-size: 0.65rem;" title="Lembaga Induk (Satmikal)"></i>
                                                    @endif
                                                    {{ nama_lembaga($sk->lembaga_id, true) }}
                                                    @if ($sk->jabatan)
                                                        <span class="opacity-75 small">({{ Str::limit($sk->nama_jabatan, 14) }})</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="badge bg-secondary" wire:click="kelolaSk('{{ $p->id }}')" style="cursor: pointer;" title="Klik untuk kelola SK penugasan">
                                            {{ nama_lembaga($p->lembaga_id, true) }}
                                        </span>
                                    @endif
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
                                        @if (!$p->user && auth()->check() && auth()->user()->isAdmin())
                                            <button wire:click="openBuatAkun('{{ $p->id }}')" class="btn btn-outline-success" title="Buat Akun Pegawai">
                                                <i class="bi bi-person-plus"></i>
                                            </button>
                                        @endif
                                        <button wire:click="show('{{ $p->id }}')" class="btn btn-outline-info" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button wire:click="openHistory('{{ $p->id }}')" class="btn btn-outline-secondary" title="Riwayat Perubahan">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <button wire:click="edit('{{ $p->id }}')" class="btn btn-outline-warning" title="Edit Pegawai">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button wire:click="kelolaSk('{{ $p->id }}')" class="btn btn-outline-primary" title="Kelola SK Penugasan ({{ $p->skPenugasan->count() }} SK)">
                                            <i class="bi bi-file-earmark-text"></i>
                                            @if ($p->skPenugasan->count() > 0)
                                                <span class="badge bg-primary text-white ms-1" style="font-size: 0.65rem;">{{ $p->skPenugasan->count() }}</span>
                                            @endif
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
            </div>

            <div class="mt-4">
                {{ $pegawaiList->links() }}
            </div>
        </div>
    </div>
    @endif

    @if ($action === 'create' || $action === 'edit')
        {{-- SPA IN-PAGE FORM VIEW --}}
        <div class="glass-panel rounded-3xl p-5 md:p-8 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl transition-all">
            {{-- Form Header & Quick Back --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-200/80 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl {{ $action === 'edit' ? 'bg-amber-500/10 text-amber-500' : 'bg-sky-500/10 text-sky-500 dark:text-cyan-400' }} flex items-center justify-center text-2xl shadow-inner">
                        <i class="bi {{ $action === 'edit' ? 'bi-pencil-square' : 'bi-person-plus-fill' }}"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 font-display">
                            {{ $action === 'edit' ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $action === 'edit' ? 'Perbarui informasi dan data kepegawaian' : 'Isi formulir lengkap untuk mendaftarkan pendidik atau tenaga kependidikan' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-2">
                        <i class="bi bi-x-lg"></i> Batal / Kembali
                    </button>
                    <button type="button" wire:click="{{ $action === 'edit' ? 'update' : 'store' }}" class="btn-logo-gradient px-5 py-2 rounded-xl text-xs font-bold shadow-md flex items-center gap-2">
                        <i class="bi bi-check2-circle text-base"></i> {{ $action === 'edit' ? 'Perbarui Data' : 'Simpan Data' }}
                    </button>
                </div>
            </div>

            <form wire:submit="{{ $action === 'edit' ? 'update' : 'store' }}" class="space-y-6">
                {{-- Section 1: Data Pokok Pegawai --}}
                <div class="p-4 md:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-cyan-400 font-display mb-4 flex items-center gap-2">
                        <i class="bi bi-person-vcard text-base"></i> 1. Data Pokok & Identitas Pegawai
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-3">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Gelar Depan</label>
                            <input wire:model="gelar_depan" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Contoh: Dr., Drs., H.">
                            @error('gelar_depan') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="md:col-span-6">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input wire:model="nama" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl font-semibold" placeholder="Nama lengkap tanpa gelar" required>
                            @error('nama') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="md:col-span-3">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Gelar Belakang</label>
                            <input wire:model="gelar_belakang" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Contoh: M.Pd., S.Pd.">
                            @error('gelar_belakang') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Jenis Kelamin <span class="text-rose-500">*</span></label>
                            <div class="flex gap-4 pt-1">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    <input wire:model="form_jenis_kelamin" type="radio" value="l" class="form-check-input text-sky-600 focus:ring-sky-500">
                                    <span>Laki-laki (L)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    <input wire:model="form_jenis_kelamin" type="radio" value="p" class="form-check-input text-pink-600 focus:ring-pink-500">
                                    <span>Perempuan (P)</span>
                                </label>
                            </div>
                            @error('form_jenis_kelamin') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">NIY (Nomor Induk Yayasan)</label>
                            <input wire:model="niy" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Otomatis jika kosong">
                            <span class="text-[11px] text-slate-400 mt-0.5 block">Dikosongkan jika ingin dibuat otomatis</span>
                            @error('niy') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">NUPTK</label>
                            <input wire:model="nuptk" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Nomor Unik Pendidik & Tenaga Kependidikan">
                            @error('nuptk') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">NIK (KTP)</label>
                            <input wire:model="nik" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Nomor Induk Kependudukan">
                            @error('nik') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Tempat Lahir</label>
                            <input wire:model="tempat_lahir" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Tempat Lahir">
                            @error('tempat_lahir') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Tanggal Lahir</label>
                            <input type="date" wire:model="tanggal_lahir" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                            @error('tanggal_lahir') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Lembaga, TMT, Status — dipindah dari Section Penugasan --}}
                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Lembaga Induk / Satmikal Utama <span class="text-rose-500">*</span></label>
                            <select wire:model="form_lembaga_id" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                <option value="">-- Pilih Lembaga --</option>
                                @foreach ($daftarLembaga as $lid => $lname)
                                    <option value="{{ $lid }}">{{ $lname }}</option>
                                @endforeach
                            </select>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">Penugasan di lembaga lain dikelola lewat SK Penugasan.</span>
                            @error('form_lembaga_id') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">TMT (Mulai Bertugas)</label>
                            <input type="date" wire:model="tmt" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                            @error('tmt') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-4">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Status Kepegawaian (Yayasan) <span class="text-rose-500">*</span></label>
                            <select wire:model="form_status" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                <option value="">-- Pilih Status --</option>
                                @foreach ($statusOptions as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">Status non-aktif/pensiun/keluar otomatis menyelesaikan penugasan SK aktif.</span>
                            @error('form_status') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Section 2: Kontak, Foto & Domisili --}}
                <div class="p-4 md:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 font-display mb-4 flex items-center gap-2">
                        <i class="bi bi-geo-alt text-base"></i> 2. Kontak, Foto & Domisili
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Nomor HP / WhatsApp</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-emerald-600 dark:text-emerald-400">
                                    <i class="bi bi-whatsapp"></i>
                                </span>
                                <input wire:model="no_hp" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-e-xl" placeholder="08xxxxxxxxxx">
                            </div>
                            @error('no_hp') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-6">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Foto Profil Pegawai</label>
                            <input type="file" wire:model="foto" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" accept="image/*">
                            <div wire:loading wire:target="foto" class="mt-1 text-xs text-sky-500 flex items-center gap-1.5">
                                <span class="spinner-border spinner-border-sm"></span> Mengunggah foto...
                            </div>
                            @error('foto') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror

                            <div class="mt-2.5">
                                @if ($foto && !is_string($foto))
                                    <div class="flex items-center gap-3 p-2 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-900">
                                        <img src="{{ $foto->temporaryUrl() }}" class="w-14 h-14 rounded-xl object-cover border border-sky-300 shadow-xs">
                                        <div>
                                            <span class="text-xs font-semibold text-sky-700 dark:text-sky-300 block">Preview foto baru</span>
                                            <span class="text-[11px] text-slate-400">Siap disimpan</span>
                                        </div>
                                    </div>
                                @elseif ($existing_foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($existing_foto))
                                    <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                        <img src="{{ asset('storage/' . $existing_foto) }}" class="w-14 h-14 rounded-xl object-cover border border-slate-300 dark:border-slate-600 shadow-xs">
                                        <div>
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 block">Foto tersimpan</span>
                                            <span class="text-[11px] text-slate-400">Pilih berkas baru untuk mengganti</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-12">
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Alamat Domisili Lengkap</label>
                            <textarea wire:model="alamat" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" rows="3" placeholder="Alamat domisili lengkap jalan, RT/RW, kelurahan, kecamatan, dsb."></textarea>
                            @error('alamat') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Bottom Action Bar --}}
                <div class="flex items-center justify-between pt-4 border-t border-slate-200/80 dark:border-slate-700/60">
                    <button type="button" wire:click="resetForm" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-2">
                        <i class="bi bi-x-circle"></i> Batal
                    </button>
                    <button type="submit" class="btn-logo-gradient px-6 py-2.5 rounded-xl text-xs font-bold shadow-md flex items-center gap-2">
                        <i class="bi bi-save"></i> {{ $action === 'edit' ? 'Perbarui Pegawai' : 'Simpan Pegawai' }}
                    </button>
                </div>
            </form>
        </div>
    @elseif ($action === 'show')
        {{-- SPA IN-PAGE DETAIL VIEW --}}
        @php
            $pData = App\Models\Pegawai::with(['skPenugasan', 'user'])->find($showId);
        @endphp
        @if ($pData)
            <div class="glass-panel rounded-3xl p-5 md:p-8 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl transition-all">
                {{-- Detail Top Bar --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-200/80 dark:border-slate-700/60">
                    <div class="flex items-center gap-4">
                        @if ($pData->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pData->foto))
                            <img src="{{ asset('storage/' . $pData->foto) }}" alt="Foto" class="w-16 h-16 rounded-2xl object-cover shadow-md border-2 border-sky-500/30">
                        @else
                            <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bg-gradient-to-tr from-pink-500 to-rose-400' : 'bg-gradient-to-tr from-sky-500 to-cyan-500' }} shadow-md text-2xl font-bold">
                                <i class="bi {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bi-gender-female' : 'bi-gender-male' }}"></i>
                            </div>
                        @endif
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 font-display">
                                {!! setNamaPegawai($pData->nama, $pData->gelar_depan, $pData->gelar_belakang) !!}
                            </h3>
                            <div class="flex items-center gap-2 flex-wrap mt-1">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    NIY: {{ $pData->niy ?: '-' }}
                                </span>
                                @if ($pData->nuptk)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-900">
                                        NUPTK: {{ $pData->nuptk }}
                                    </span>
                                @endif
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ strtolower($pData->jenis_kelamin) === 'p' ? 'bg-pink-50 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 border border-pink-200 dark:border-pink-900' : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900' }}">
                                    {{ $pData->jenis_kelamin_label }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-white dark:bg-slate-700">
                                    {{ nama_lembaga($pData->lembaga_id) }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $pData->status == '1' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                    {{ $pData->status_label }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if (!$pData->user && auth()->check() && auth()->user()->isAdmin())
                            <button type="button" wire:click="openBuatAkun('{{ $pData->id }}')" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                <i class="bi bi-person-plus"></i> Buat Akun
                            </button>
                        @endif
                        <button type="button" wire:click="openHistory('{{ $pData->id }}')" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all flex items-center gap-1.5 border border-slate-200 dark:border-slate-700">
                            <i class="bi bi-clock-history text-cyan-500"></i> Riwayat
                        </button>
                        <button type="button" wire:click="kelolaSk('{{ $pData->id }}')" class="px-3.5 py-2 rounded-xl bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-900/40 text-sky-700 dark:text-cyan-300 text-xs font-bold transition-all flex items-center gap-1.5 border border-sky-200 dark:border-sky-800">
                            <i class="bi bi-file-earmark-medical text-sky-500"></i> Kelola SK
                        </button>
                        <button type="button" wire:click="edit('{{ $pData->id }}')" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <button type="button" wire:click="resetForm" class="px-3.5 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-1.5">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </button>
                    </div>
                </div>

                {{-- Detail Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-building text-sky-500"></i> Lembaga Satmikal Induk
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            {{ nama_lembaga($pData->lembaga_id) }}
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-award text-amber-500"></i> NUPTK
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            {{ $pData->nuptk ?: '-' }}
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-card-text text-purple-500"></i> NIK (KTP)
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            {{ $pData->nik ?: '-' }}
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-calendar-event text-pink-500"></i> Tempat, Tanggal Lahir
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            {{ $pData->tempat_lahir ?: '-' }}, {{ $pData->tanggal_lahir ? $pData->tanggal_lahir->format('d F Y') : '-' }}
                            @if ($pData->tanggal_lahir)
                                <span class="text-xs text-slate-400 font-normal">({{ $pData->tanggal_lahir->age }} th)</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-clock-history text-emerald-500"></i> TMT (Mulai Bertugas)
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            {{ $pData->tmt ? $pData->tmt->format('d F Y') : '-' }}
                            @if ($pData->tmt)
                                <span class="text-xs text-slate-400 font-normal">({{ $pData->tmt->diffForHumans(null, true) }})</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-whatsapp text-emerald-500"></i> Kontak / WhatsApp
                        </div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                            @if ($pData->no_hp)
                                @php
                                    $cleanHp = preg_replace('/[^0-9]/', '', $pData->no_hp);
                                    $waNum = str_starts_with($cleanHp, '0') ? '62' . substr($cleanHp, 1) : $cleanHp;
                                @endphp
                                <a href="https://wa.me/{{ $waNum }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
                                    <i class="bi bi-whatsapp"></i> {{ $pData->no_hp }}
                                </a>
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <div class="md:col-span-2 lg:col-span-2 p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1 flex items-center gap-1.5">
                            <i class="bi bi-geo-alt text-rose-500"></i> Alamat Domisili
                        </div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 text-sm">
                            {{ $pData->alamat ?: '-' }}
                        </div>
                    </div>

                    {{-- Akun Card --}}
                    <div class="p-4 rounded-2xl {{ $pData->user ? 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200/80 dark:border-emerald-800/60' : 'bg-slate-50/70 dark:bg-slate-800/40 border-slate-200/80 dark:border-slate-700/60' }} border shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="text-xs {{ $pData->user ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }} font-semibold mb-1 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <i class="bi {{ $pData->user ? 'bi-person-check text-emerald-500' : 'bi-person-x text-slate-400' }}"></i> Akun SIMBA
                                </span>
                                @if ($pData->user)
                                    <span class="badge bg-emerald-600 text-white font-mono uppercase" style="font-size: 0.65rem;">{{ $pData->user->role }}</span>
                                @endif
                            </div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                                {{ $pData->user ? $pData->user->email : 'Belum Memiliki Akun' }}
                            </div>
                            @if ($pData->user)
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Nama User: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $pData->user->name }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between">
                            @if ($pData->user)
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold"><i class="bi bi-shield-check me-1"></i>Terhubung</span>
                                @if (auth()->check() && auth()->user()->isAdmin())
                                    <button type="button" wire:click="putusAkun('{{ $pData->id }}')" wire:confirm="Putus sambungan akun ini dari pegawai?" class="text-xs text-rose-600 hover:text-rose-700 font-semibold hover:underline">
                                        Putus Akun
                                    </button>
                                @endif
                            @else
                                <span class="text-[11px] text-slate-400">Tidak ada akses login</span>
                                @if (auth()->check() && auth()->user()->isAdmin())
                                    <button type="button" wire:click="openBuatAkun('{{ $pData->id }}')" class="btn btn-xs btn-outline-success rounded-lg font-bold">
                                        <i class="bi bi-person-plus me-1"></i>Buat Akun
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Riwayat SK Penugasan di Halaman --}}
                <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-cyan-400 font-display flex items-center gap-2">
                            <i class="bi bi-file-earmark-medical text-base"></i> Riwayat Lembaga Bertugas & SK Penugasan
                        </h4>
                        <button type="button" class="btn-logo-gradient px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5" wire:click="kelolaSk('{{ $pData->id }}')">
                            <i class="bi bi-gear"></i> Kelola SK Penugasan
                        </button>
                    </div>

                    <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                        <table class="table table-hover align-middle mb-0 text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-800 font-display">
                                <tr>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Lembaga</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Jabatan / Tugas</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">No. SK & Tanggal</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Masa Tugas</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300">Status</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300">Berkas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pData->skPenugasan as $sk)
                                    <tr>
                                        <td>
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ nama_lembaga($sk->lembaga_id) }}</span>
                                            @if ($sk->is_induk)
                                                <span class="ms-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-cyan-300 border border-sky-200 dark:border-sky-800">
                                                    <i class="bi bi-star-fill text-amber-400 me-0.5"></i>Induk
                                                </span>
                                            @else
                                                <span class="ms-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                    Tambahan
                                                </span>
                                            @endif
                                        </td>
                                        <td class="font-medium text-slate-700 dark:text-slate-300">{{ $sk->nama_jabatan ?: '-' }}</td>
                                        <td>
                                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $sk->nomor_sk ?: '-' }}</div>
                                            @if ($sk->tanggal_sk)
                                                <div class="text-[11px] text-slate-400">Tgl: {{ $sk->tanggal_sk->format('d/m/Y') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-slate-700 dark:text-slate-300">TMT: {{ $sk->tmt_tugas ? $sk->tmt_tugas->format('d/m/Y') : '-' }}</div>
                                            @if ($sk->tmt_selesai)
                                                <div class="text-[11px] text-slate-400">s/d {{ $sk->tmt_selesai->format('d/m/Y') }}</div>
                                            @else
                                                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">s/d Sekarang</div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $sk->status == '1' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                                {{ $sk->status_label }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($sk->file_sk && \Illuminate\Support\Facades\Storage::disk('public')->exists($sk->file_sk))
                                                <a href="{{ asset('storage/' . $sk->file_sk) }}" target="_blank" class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 text-xs font-semibold inline-flex items-center gap-1 border border-rose-200 dark:border-rose-900" title="Unduh Berkas SK">
                                                    <i class="bi bi-file-earmark-pdf"></i> Unduh
                                                </a>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-slate-400 py-3 italic">Belum ada data penugasan tercatat</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Metadata & Bottom Back --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 border-t border-slate-200/80 dark:border-slate-700/60 text-xs text-slate-400">
                    <div>
                        ID Pegawai: <span class="font-mono text-slate-500">{{ $pData->id }}</span>
                        • Terdaftar: {{ $pData->created_at ? $pData->created_at->format('d/m/Y H:i') : '-' }}
                        • Pembaruan: {{ $pData->updated_at ? $pData->updated_at->format('d/m/Y H:i') : '-' }}
                    </div>
                    <div>
                        <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold transition-all flex items-center gap-2">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Pegawai
                        </button>
                    </div>
                </div>
            </div>
        @endif

    @elseif ($action === 'sk')
        {{-- SPA IN-PAGE SK PENUGASAN MANAGEMENT VIEW --}}
        @if ($selectedPegawai)
            <div class="glass-panel rounded-3xl p-5 md:p-8 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl transition-all">
                {{-- SK View Header --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-200/80 dark:border-slate-700/60">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-500 dark:text-cyan-400 flex items-center justify-center text-2xl shadow-inner">
                            <i class="bi bi-file-earmark-medical"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 font-display">
                                Kelola SK Penugasan Lembaga
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Manajemen penugasan lembaga induk dan tugas tambahan di lingkungan yayasan
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="show('{{ $selectedPegawai->id }}')" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-1.5 border border-slate-200 dark:border-slate-700">
                            <i class="bi bi-person-vcard text-sky-500"></i> Detail Pegawai
                        </button>
                        <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-2">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                        </button>
                    </div>
                </div>

                {{-- Selected Pegawai Profile Summary Banner --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl mb-6 bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                    <div class="flex items-center gap-3">
                        @if ($selectedPegawai->foto && Storage::disk('public')->exists($selectedPegawai->foto))
                            <img src="{{ asset('storage/' . $selectedPegawai->foto) }}" class="w-12 h-12 rounded-xl object-cover shadow-sm border border-slate-200 dark:border-slate-700">
                        @else
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white {{ strtolower($selectedPegawai->jenis_kelamin) === 'p' ? 'bg-pink-500' : 'bg-sky-500' }} shadow-sm text-xl font-bold">
                                <i class="bi {{ strtolower($selectedPegawai->jenis_kelamin) === 'p' ? 'bi-gender-female' : 'bi-gender-male' }}"></i>
                            </div>
                        @endif
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-100 font-display text-base">
                                {!! setNamaPegawai($selectedPegawai->nama, $selectedPegawai->gelar_depan, $selectedPegawai->gelar_belakang) !!}
                            </div>
                            <div class="text-xs text-slate-400 flex items-center gap-2 flex-wrap">
                                <span>NIY: {{ $selectedPegawai->niy ?: '-' }}</span>
                                <span>•</span>
                                <span>Lembaga Induk: {{ nama_lembaga($selectedPegawai->lembaga_id) }}</span>
                                <span>•</span>
                                <span>{{ $selectedPegawai->status_label }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="px-3 py-1 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-cyan-300 border border-sky-200 dark:border-sky-800 text-xs font-bold">
                            Total Penugasan: {{ $selectedPegawai->skPenugasan->count() }}
                        </span>
                    </div>
                </div>

                {{-- Table Daftar SK yang Tersimpan --}}
                <div class="mb-6">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 font-display mb-3 flex items-center gap-2">
                        <i class="bi bi-list-check text-sky-500"></i> Riwayat Lembaga Bertugas & SK Penugasan
                    </h4>
                    <div class="table-responsive rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                        <table class="table table-hover align-middle mb-0 text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-800 font-display">
                                <tr>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Lembaga Penugasan</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Jabatan / Tugas</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Nomor SK & Tgl</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Masa Penugasan</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300">Induk</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300">Status</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300">Dokumen</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selectedPegawai->skPenugasan as $skItem)
                                    <tr class="{{ $sk_id === $skItem->id ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                                        <td>
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ nama_lembaga($skItem->lembaga_id) }}</span>
                                            <span class="block text-[11px] text-slate-400">{{ nama_lembaga($skItem->lembaga_id, true) }}</span>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $skItem->nama_jabatan ?: '-' }}</span>
                                            @if ($skItem->keterangan)
                                                <div class="text-slate-400 text-[11px]">{{ $skItem->keterangan }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $skItem->nomor_sk ?: '-' }}</div>
                                            @if ($skItem->tanggal_sk)
                                                <div class="text-[11px] text-slate-400">SK: {{ $skItem->tanggal_sk->format('d/m/Y') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-slate-700 dark:text-slate-300">TMT: {{ $skItem->tmt_tugas ? $skItem->tmt_tugas->format('d/m/Y') : '-' }}</div>
                                            @if ($skItem->tmt_selesai)
                                                <div class="text-[11px] text-slate-400">s/d {{ $skItem->tmt_selesai->format('d/m/Y') }}</div>
                                            @else
                                                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">s/d Sekarang</div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($skItem->is_induk)
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-cyan-300 border border-sky-200 dark:border-sky-800 inline-flex items-center gap-1">
                                                    <i class="bi bi-star-fill text-amber-400"></i> Lembaga Induk
                                                </span>
                                            @else
                                                <button type="button" wire:click="setIndukSk('{{ $skItem->id }}')" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 text-[10px] font-semibold border border-slate-200 dark:border-slate-700" title="Jadikan sebagai Lembaga Induk">
                                                    <i class="bi bi-star me-0.5"></i> Jadikan Induk
                                                </button>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $skItem->status == '1' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                                {{ $skItem->status == '1' ? 'Aktif' : 'Selesai' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($skItem->file_sk && Storage::disk('public')->exists($skItem->file_sk))
                                                <a href="{{ asset('storage/' . $skItem->file_sk) }}" target="_blank" class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 text-xs font-semibold inline-flex items-center gap-1 border border-rose-200 dark:border-rose-900" title="Lihat / Download File SK">
                                                    <i class="bi bi-file-earmark-pdf"></i> Unduh
                                                </a>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button" wire:click="editSk('{{ $skItem->id }}')" class="p-1 px-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 hover:bg-amber-100 border border-amber-200 dark:border-amber-900" title="Edit SK Penugasan">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" wire:click="deleteSk('{{ $skItem->id }}')" wire:confirm="Hapus penugasan di lembaga ini?" class="p-1 px-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 border border-rose-200 dark:border-rose-900" title="Hapus SK Penugasan">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-slate-400 py-4 italic">
                                            Belum ada SK penugasan lembaga untuk pegawai ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Form Input / Edit SK Penugasan --}}
                <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border {{ $isEditingSk ? 'border-amber-400 dark:border-amber-500/40' : 'border-slate-200/80 dark:border-slate-700/60' }} shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider {{ $isEditingSk ? 'text-amber-600 dark:text-amber-400' : 'text-sky-600 dark:text-cyan-400' }} font-display flex items-center gap-2">
                            <i class="bi {{ $isEditingSk ? 'bi-pencil-square' : 'bi-plus-circle-fill' }}"></i>
                            {{ $isEditingSk ? 'Edit SK Penugasan Lembaga' : 'Tambah SK Penugasan / Penugasan di Lembaga Lain' }}
                        </h4>
                        @if ($isEditingSk)
                            <button type="button" wire:click="cancelEditSk" class="px-3 py-1 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold">
                                Batal Edit (Tambah Baru)
                            </button>
                        @endif
                    </div>

                    <form wire:submit.prevent="saveSk" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Lembaga Penugasan <span class="text-rose-500">*</span></label>
                                <select wire:model="sk_lembaga_id" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                    <option value="">-- Pilih Lembaga --</option>
                                    @foreach ($daftarLembaga as $lid => $lname)
                                        <option value="{{ $lid }}">{{ $lname }}</option>
                                    @endforeach
                                </select>
                                @error('sk_lembaga_id') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Jabatan / Tugas</label>
                                <select wire:model="sk_jabatan" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach ($daftarJabatan as $jid => $jname)
                                        <option value="{{ $jid }}">{{ $jname }}</option>
                                    @endforeach
                                </select>
                                @error('sk_jabatan') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-4">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Nomor SK Penugasan</label>
                                <input wire:model="sk_nomor" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Nomor Surat Keputusan">
                                @error('sk_nomor') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Tanggal SK Ditetapkan</label>
                                <input type="date" wire:model="sk_tanggal" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                                @error('sk_tanggal') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">TMT Tugas (Mulai)</label>
                                <input type="date" wire:model="sk_tmt_tugas" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                                @error('sk_tmt_tugas') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">TMT Selesai (Opsional)</label>
                                <input type="date" wire:model="sk_tmt_selesai" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                                <span class="text-[11px] text-slate-400 mt-0.5 block">Kosongkan jika masih aktif / tetap</span>
                                @error('sk_tmt_selesai') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Masa Berlaku SK <span class="text-rose-500">*</span></label>
                                <select wire:model="sk_status" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" required>
                                    <option value="1">Aktif / Berlaku</option>
                                    <option value="0">Selesai / Kedaluwarsa</option>
                                </select>
                                <span class="text-[11px] text-slate-400 mt-0.5 block">Jika aktif, otomatis menyelaraskan status aktif pegawai.</span>
                                @error('sk_status') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Unggah Dokumen SK (PDF / Gambar, Max 5MB)</label>
                                <input type="file" wire:model="sk_file" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" accept=".pdf,image/*">
                                <div wire:loading wire:target="sk_file" class="text-xs text-sky-500 mt-1 flex items-center gap-1.5">
                                    <span class="spinner-border spinner-border-sm"></span> Mengunggah berkas SK...
                                </div>
                                @error('sk_file') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                                @if ($sk_existing_file && Storage::disk('public')->exists($sk_existing_file))
                                    <div class="mt-2 text-xs flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                        <i class="bi bi-file-earmark-check text-emerald-500"></i> File SK tersimpan:
                                        <a href="{{ asset('storage/' . $sk_existing_file) }}" target="_blank" class="text-sky-600 hover:underline font-semibold">Lihat Dokumen</a>
                                    </div>
                                @endif
                            </div>

                            <div class="md:col-span-6">
                                <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Keterangan Tambahan</label>
                                <input wire:model="sk_keterangan" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Catatan atau keterangan penugasan">
                                @error('sk_keterangan') <span class="text-danger text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:col-span-12">
                                <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                    <input wire:model="sk_is_induk" type="checkbox" class="form-check-input text-sky-600">
                                    <div>
                                        <span class="text-xs font-bold text-sky-700 dark:text-cyan-400 flex items-center gap-1">
                                            <i class="bi bi-star-fill text-amber-400"></i> Jadikan Lembaga ini sebagai Lembaga Induk (Satmikal Utama)
                                        </span>
                                        <span class="text-[11px] text-slate-400 block">Jika dicentang, status lembaga induk pada penugasan lainnya akan dialihkan ke lembaga ini.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/80 dark:border-slate-700/60">
                            @if ($isEditingSk)
                                <button type="button" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold" wire:click="cancelEditSk">
                                    Batal Edit
                                </button>
                                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-sm flex items-center gap-1.5" wire:loading.attr="disabled">
                                    <i class="bi bi-save"></i> Perbarui SK Penugasan
                                </button>
                            @else
                                <button type="submit" class="btn-logo-gradient px-5 py-2 rounded-xl text-xs font-bold shadow-md flex items-center gap-1.5" wire:loading.attr="disabled">
                                    <i class="bi bi-plus-circle"></i> Tambahkan SK Penugasan
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    {{-- MODAL BULK UPDATE --}}
    @if ($showBulkUpdateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="bg-gradient-to-r from-sky-600 to-cyan-600 text-white p-4 flex items-center justify-between">
                    <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm">
                        <i class="bi bi-pencil-square text-cyan-200"></i> Update Data Massal
                    </h5>
                    <button type="button" wire:click="closeBulkUpdate" class="text-white hover:text-slate-200">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        Perubahan akan diterapkan pada <strong class="text-sky-600 dark:text-cyan-400 font-bold">{{ count($checkedPegawai) }}</strong> pegawai yang dipilih.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Pilih Lembaga Baru</label>
                        <select wire:model="bulkLembagaId" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($daftarLembaga as $lid => $lname)
                                <option value="{{ $lid }}">{{ $lname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Pilih Status Kepegawaian Baru</label>
                        <select wire:model="bulkStatus" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($statusOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-slate-400 mt-1 block">Status non-aktif/pensiun/keluar otomatis menyelesaikan penugasan SK aktif.</span>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeBulkUpdate" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold">
                            Batal
                        </button>
                        <button type="button" wire:click="bulkUpdate" class="btn-logo-gradient px-4 py-2 rounded-xl text-xs font-bold shadow-md flex items-center gap-1.5" wire:loading.attr="disabled">
                            <i class="bi bi-check2-circle"></i> Simpan Perubahan Massal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL BULK DELETE --}}
    @if ($showBulkDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl border border-rose-500/30 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="bg-gradient-to-r from-rose-600 to-red-700 text-white p-4 flex items-center justify-between">
                    <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm">
                        <i class="bi bi-exclamation-triangle text-amber-300"></i> Konfirmasi Hapus Massal
                    </h5>
                    <button type="button" wire:click="cancelBulkDelete" class="text-white hover:text-slate-200">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p class="text-slate-700 dark:text-slate-300 text-sm">Anda yakin ingin menghapus <strong class="text-rose-500">{{ count($checkedPegawai) }}</strong> pegawai yang dipilih?</p>
                    <p class="text-rose-600 dark:text-rose-400 text-xs font-semibold flex items-center gap-1.5 my-2">
                        <i class="bi bi-info-circle"></i> Tindakan ini permanen dan tidak dapat dibatalkan!
                    </p>

                    <div class="my-4">
                        <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-400">
                            Ketik karakter berikut untuk konfirmasi: 
                            <strong class="font-mono text-base ms-1 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-900 user-select-none">{{ $randomConfirmString }}</strong>
                        </label>
                        <input type="text" wire:model="confirmText" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" placeholder="Ketik karakter konfirmasi..." autocomplete="off">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelBulkDelete" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold">
                            Batal
                        </button>
                        <button type="button" wire:click="bulkDelete" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-500/20" wire:loading.attr="disabled">
                            <i class="bi bi-trash me-1"></i> Hapus Pegawai Terpilih
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL RIWAYAT PERUBAHAN PEGAWAI --}}
    @if ($historyPegawai)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="w-full max-w-4xl max-h-[90vh] rounded-3xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/80">
                    <div>
                        <h5 class="font-bold flex items-center gap-2 mb-0 text-slate-800 dark:text-slate-100 text-sm font-display">
                            <i class="bi bi-clock-history text-cyan-500"></i>
                            Riwayat Perubahan Data Pegawai
                        </h5>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            <strong class="text-slate-800 dark:text-slate-200">{!! setNamaPegawai($historyPegawai->nama, $historyPegawai->gelar_depan, $historyPegawai->gelar_belakang) !!}</strong>
                            <span class="mx-1">•</span> Lembaga: <span class="font-semibold">{{ nama_lembaga($historyPegawai->lembaga_id) }}</span>
                            <span class="mx-1">•</span> NIY: <span class="font-semibold">{{ $historyPegawai->niy ?? '-' }}</span>
                        </div>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1" wire:click="closeHistory">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto space-y-4">
                    @php
                        $histories = $historyPegawai->histories;
                        $latestUndoable = $histories->where('is_reverted', false)->whereIn('action', ['update', 'bulk_update', 'rollback'])->whereNotNull('old_values')->first();
                    @endphp

                    {{-- TOP ACTION / SUMMARY BAR --}}
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-500 flex items-center justify-center font-bold">
                                <i class="bi bi-journal-text text-lg"></i>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400">Total Riwayat Perubahan</div>
                                <div class="font-bold text-xs text-slate-800 dark:text-slate-200">
                                    {{ $histories->count() }} aktivitas tercatat
                                </div>
                            </div>
                        </div>

                        @if ($latestUndoable)
                            <button type="button" 
                                    wire:click="undoLatestHistory('{{ $historyPegawai->id }}')"
                                    wire:confirm="Yakin ingin membatalkan (undo) perubahan terakhir pada pegawai ini?"
                                    class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span>Undo Perubahan Terakhir</span>
                            </button>
                        @endif
                    </div>

                    {{-- TIMELINE LIST --}}
                    <div class="table-responsive rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                        <table class="table table-hover align-middle mb-0 text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-800 font-display">
                                <tr>
                                    <th style="width: 140px;" class="py-2.5 text-slate-700 dark:text-slate-300">Waktu & User</th>
                                    <th style="width: 100px;" class="py-2.5 text-slate-700 dark:text-slate-300">Aksi</th>
                                    <th class="py-2.5 text-slate-700 dark:text-slate-300">Rincian Perubahan (Sebelum <i class="bi bi-arrow-right"></i> Sesudah)</th>
                                    <th class="text-center py-2.5 text-slate-700 dark:text-slate-300" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($histories as $h)
                                    <tr class="{{ $h->is_reverted ? 'opacity-50' : '' }}">
                                        <td>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ $h->created_at->format('d/m/Y H:i') }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $h->created_at->diffForHumans() }}</div>
                                            <div class="text-[11px] text-cyan-600 dark:text-cyan-400 flex items-center gap-1 mt-0.5">
                                                <i class="bi bi-person-fill"></i> {{ $h->user->name ?? 'Sistem' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($h->action === 'create')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">Tambah</span>
                                            @elseif ($h->action === 'update')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-cyan-400 border border-sky-200 dark:border-sky-800">Pembaruan</span>
                                            @elseif ($h->action === 'bulk_update')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800">Massal</span>
                                            @elseif ($h->action === 'rollback')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">Undo</span>
                                            @elseif ($h->action === 'sk_create')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-800">Tambah SK</span>
                                            @elseif ($h->action === 'sk_update')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">Update SK</span>
                                            @elseif ($h->action === 'sk_delete')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">Hapus SK</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">{{ $h->action }}</span>
                                            @endif

                                            @if ($h->is_reverted)
                                                <div class="mt-1">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 border border-rose-200 dark:border-rose-900 inline-block">
                                                        Di-undo
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($h->description)
                                                <div class="font-semibold text-slate-700 dark:text-slate-300 mb-1 text-xs">
                                                    {{ $h->description }}
                                                </div>
                                            @endif

                                            @if ($h->old_values && $h->new_values)
                                                <div class="flex flex-col gap-1 mt-1">
                                                    @foreach ($h->new_values as $field => $newVal)
                                                        @if (in_array($field, ['sk_id', 'id', 'pegawai_id'])) @continue @endif
                                                        @php
                                                             $oldVal = $h->old_values[$field] ?? null;
                                                             $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                             $oldFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $oldVal);
                                                             $newFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $newVal);
                                                        @endphp
                                                        <div class="p-1 px-2 rounded-lg bg-slate-100/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-[11px] flex flex-wrap items-center gap-1.5">
                                                            <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                            <span class="line-through text-rose-500 opacity-80">{!! $oldFormatted !!}</span>
                                                            <span class="text-slate-400">→</span>
                                                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{!! $newFormatted !!}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif ($h->action === 'sk_delete' && $h->old_values)
                                                <div class="flex flex-col gap-1 mt-1">
                                                    <span class="text-[11px] text-rose-500 font-semibold">Data SK yang dihapus:</span>
                                                    @foreach ($h->old_values as $field => $oldVal)
                                                        @if (in_array($field, ['sk_id', 'id', 'pegawai_id']) || $oldVal === null || $oldVal === '') @continue @endif
                                                        @php
                                                             $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                             $oldFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $oldVal);
                                                        @endphp
                                                        <div class="p-1 px-2 rounded-lg bg-slate-100/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-[11px] flex items-center gap-1.5">
                                                            <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                            <span class="text-rose-600">{!! $oldFormatted !!}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif ($h->action === 'sk_create' && $h->new_values)
                                                <div class="flex flex-col gap-1 mt-1">
                                                    <span class="text-[11px] text-teal-600 dark:text-teal-400 font-semibold">Rincian SK baru:</span>
                                                    @foreach ($h->new_values as $field => $newVal)
                                                        @if (in_array($field, ['sk_id', 'id', 'pegawai_id']) || $newVal === null || $newVal === '') @continue @endif
                                                        @php
                                                             $fieldLabel = App\Models\PegawaiHistory::formatFieldName($field);
                                                             $newFormatted = App\Models\PegawaiHistory::formatFieldValue($field, $newVal);
                                                        @endphp
                                                        <div class="p-1 px-2 rounded-lg bg-slate-100/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-[11px] flex items-center gap-1.5">
                                                            <strong class="text-slate-600 dark:text-slate-400">{{ $fieldLabel }}:</strong>
                                                            <span class="text-emerald-600 font-semibold">{!! $newFormatted !!}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif ($h->action === 'create')
                                                <span class="text-xs text-slate-400 italic">Pembuatan data awal pegawai</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if (!$h->is_reverted && $h->action !== 'create' && ($h->old_values || $h->action === 'sk_create'))
                                                <button type="button" 
                                                        wire:click="undoHistory({{ $h->id }})"
                                                        wire:confirm="Yakin ingin membatalkan (undo) perubahan ini?"
                                                        class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 hover:bg-amber-100 text-xs font-bold border border-amber-200 dark:border-amber-900"
                                                        title="Undo Perubahan Ini">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Undo
                                                </button>
                                            @elseif ($h->is_reverted)
                                                <span class="text-[10px] text-slate-400 italic">
                                                    Di-undo {{ $h->reverted_at ? $h->reverted_at->diffForHumans() : '' }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 text-xs">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-slate-400 py-4 italic">
                                            Belum ada riwayat perubahan yang tercatat
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-3 border-t border-slate-200 dark:border-slate-800 flex justify-end bg-slate-50 dark:bg-slate-800/80">
                    <button type="button" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold" wire:click="closeHistory">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL BUAT / SAMBUNGKAN AKUN PEGAWAI --}}
    @if ($showAccountModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-emerald-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 flex items-center justify-between">
                    <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm m-0">
                        <i class="bi bi-person-plus text-emerald-200"></i> Akun Login SIMBA Pegawai
                    </h5>
                    <button type="button" wire:click="closeAccountModal" class="text-white hover:text-slate-200">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="p-5">
                    @php
                        $targetPegawai = App\Models\Pegawai::find($accountPegawaiId);
                    @endphp
                    @if ($targetPegawai)
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 mb-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white bg-gradient-to-tr from-emerald-500 to-teal-400 font-bold shadow-xs">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                    {{ $targetPegawai->nama_lengkap ?: $targetPegawai->nama }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                    NIY: {{ $targetPegawai->niy ?: '-' }} &bull; Lembaga: {{ nama_lembaga($targetPegawai->lembaga_id, true) }}
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Mode Tab --}}
                    <div class="flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800 mb-4 text-xs font-semibold">
                        <button type="button" wire:click="$set('account_type', 'new')" class="flex-1 py-1.5 rounded-lg transition-all {{ $account_type === 'new' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
                            <i class="bi bi-plus-circle me-1"></i>Buat Akun Baru
                        </button>
                        <button type="button" wire:click="$set('account_type', 'existing')" class="flex-1 py-1.5 rounded-lg transition-all {{ $account_type === 'existing' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
                            <i class="bi bi-link-45deg me-1"></i>Pilih Akun Ada
                        </button>
                    </div>

                    @if ($account_type === 'new')
                        <div class="space-y-3">
                            <div>
                                <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Nama Pengguna</label>
                                <input type="text" wire:model="account_name" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('account_name') is-invalid @enderror">
                                @error('account_name') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Alamat Email / Login</label>
                                <input type="email" wire:model="account_email" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('account_email') is-invalid @enderror">
                                @error('account_email') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Role Akun</label>
                                    <select wire:model="account_role" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('account_role') is-invalid @enderror">
                                        <option value="guru">Guru</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    @error('account_role') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Password</label>
                                    <input type="text" wire:model="account_password" class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('account_password') is-invalid @enderror">
                                    @error('account_password') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Pilih Akun yang Belum Terhubung</label>
                            <select wire:model="existing_user_id" class="form-select form-select-sm bg-slate-50/50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('existing_user_id') is-invalid @enderror">
                                <option value="">-- Pilih Akun User --</option>
                                @foreach ($availableUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }}) - {{ strtoupper($u->role) }}</option>
                                @endforeach
                            </select>
                            @error('existing_user_id') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                            @if ($availableUsers->isEmpty())
                                <span class="text-[11px] text-amber-500 mt-1 block">Tidak ada akun bebas yang belum terhubung. Silakan gunakan tab "Buat Akun Baru".</span>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2 mt-5 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" wire:click="closeAccountModal" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold">
                            Batal
                        </button>
                        <button type="button" wire:click="saveAkun" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-bold shadow-md flex items-center gap-1.5" wire:loading.attr="disabled">
                            <i class="bi bi-check-circle"></i> {{ $account_type === 'new' ? 'Buat & Sambungkan Akun' : 'Sambungkan Akun' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
