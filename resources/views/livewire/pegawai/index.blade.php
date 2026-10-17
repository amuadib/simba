@section('title', 'Data Pegawai')

<?php

use App\Models\Pegawai;
use App\Models\SkPenugasan;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PegawaiImport;
use App\Exports\PegawaiExport;
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
        $this->dispatch('show-modal-delete');
    }

    public function bulkDelete()
    {
        if (strtoupper($this->confirmText) !== $this->randomConfirmString) {
            $this->dispatch('toast', message: 'Karakter konfirmasi tidak cocok', type: 'error');
            return;
        }

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

    // --- CRUD ACTIONS PEGAWAI ---

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
        $this->dispatch('close-modal', id: 'modalPegawaiForm');
        $this->dispatch('toast', message: 'Pegawai berhasil diperbarui', type: 'success');
    }

    public function delete($id)
    {
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
        $this->dispatch('toast', message: 'Pegawai berhasil dihapus', type: 'success');
    }

    public function resetForm()
    {
        $this->reset([
            'nama', 'niy', 'nuptk', 'tmt', 'tempat_lahir', 'tanggal_lahir',
            'nik', 'alamat', 'no_hp', 'gelar_depan', 'gelar_belakang',
            'foto', 'existing_foto', 'action', 'editingId', 'showId',
            'form_nomor_sk', 'form_jabatan'
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

        if (!empty($this->bulkLembagaId)) {
            foreach ($this->checkedPegawai as $pId) {
                $p = Pegawai::find($pId);
                if ($p) {
                    $existing = $p->skPenugasan()->where('lembaga_id', $this->bulkLembagaId)->first();
                    if ($existing) {
                        $p->skPenugasan()->where('id', '!=', $existing->id)->update(['is_induk' => false]);
                        $existing->update(['is_induk' => true, 'status' => '1']);
                    } else {
                        $p->skPenugasan()->update(['is_induk' => false]);
                        $p->skPenugasan()->create([
                            'lembaga_id' => $this->bulkLembagaId,
                            'jabatan' => null,
                            'is_induk' => true,
                            'status' => '1',
                        ]);
                    }
                }
            }
        }

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

    // --- MANAJEMEN SK PENUGASAN ---

    public function kelolaSk($pegawaiId)
    {
        $this->selectedPegawaiId = $pegawaiId;
        $this->resetSkForm();
        $this->dispatch('show-modal-sk');
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
            $pegawai->skPenugasan()->where('id', '!=', $this->sk_id)->update(['is_induk' => false]);
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
        SkPenugasan::where('pegawai_id', $sk->pegawai_id)->update(['is_induk' => false]);
        $sk->update(['is_induk' => true]);
        Pegawai::where('id', $sk->pegawai_id)->update(['lembaga_id' => $sk->lembaga_id]);

        $this->dispatch('toast', message: 'Lembaga induk berhasil diatur ke ' . nama_lembaga($sk->lembaga_id), type: 'success');
    }

    public function with()
    {
        $pegawai = Pegawai::query()
            ->with('skPenugasan')
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
            ? Pegawai::with('skPenugasan')->find($this->selectedPegawaiId)
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
        ];
    }
};

?>

<div>
    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">
                Data Pegawai
            </h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                Kelola data tenaga pendidik, kependidikan & riwayat SK penugasan lembaga
            </p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

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
                    <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 self-start">
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'filter' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                @click="activeTab = 'filter'" type="button">
                            <i class="bi bi-funnel"></i> Filter & Pencarian
                        </button>
                        <button class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-display" 
                                :class="activeTab === 'import' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                @click="activeTab = 'import'" type="button">
                            <i class="bi bi-upload"></i> Import Excel
                        </button>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        @if (count($checkedPegawai) > 0)
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" data-bs-toggle="modal" data-bs-target="#modalBulkUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Update Status ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="prepareBulkDelete">
                                <i class="bi bi-trash me-1"></i> Hapus ({{ count($checkedPegawai) }})
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm rounded-xl px-3 py-1.5 text-xs font-semibold" wire:click="bulkExport">
                                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ({{ count($checkedPegawai) }})
                            </button>
                        @endif

                        <button type="button" wire:click="exportAll" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-xs font-semibold transition-all">
                            <i class="bi bi-download text-emerald-500"></i> Ekspor Semua
                        </button>

                        <button wire:click="create" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                            <i class="bi bi-plus-circle text-sm"></i> Tambah Pegawai
                        </button>
                    </div>
                </div>

                <div class="tab-content mb-4">
                    {{-- TAB FILTER --}}
                    <div x-show="activeTab === 'filter'">
                        <div class="p-3.5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/80 dark:border-cyan-500/20 shadow-inner">
                            <form wire:submit.prevent class="row g-2.5">
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text border-end-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-sky-500">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" wire:model.live.debounce.300ms="q" class="form-control form-control-sm border-start-0 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400" placeholder="Cari nama, NIY, NIK...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select wire:model.live="lembaga_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                                        <option value="">-- Semua Lembaga --</option>
                                        @foreach ($daftarLembaga as $lid => $lname)
                                            <option value="{{ $lid }}">{{ $lname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="jabatan_id" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                                        <option value="">-- Semua Jabatan --</option>
                                        @foreach ($daftarJabatan as $jid => $jname)
                                            <option value="{{ $jid }}">{{ $jname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="status" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                                        <option value="">-- Semua Status --</option>
                                        @foreach ($statusOptions as $k => $v)
                                            <option value="{{ $k }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <select wire:model.live="jenis_kelamin" class="form-select form-select-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                                        <option value="">-- JK --</option>
                                        <option value="l">L</option>
                                        <option value="p">P</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" wire:click="resetPage" class="btn btn-sm btn-outline-secondary w-100 rounded-xl" title="Reset Filter">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- TAB IMPORT --}}
                    <div x-show="activeTab === 'import'" style="display: none;">
                        <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/80 dark:border-cyan-500/20 shadow-inner">
                            <form wire:submit="import" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <input type="file" wire:model="importFile" class="form-control form-control-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100" accept=".xlsx,.xls,.csv" required>
                                    @error('importFile')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-warning btn-sm w-100 rounded-xl fw-bold" wire:loading.attr="disabled">
                                        <i class="bi bi-upload me-1"></i> IMPORT EXCEL
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="exportTemplate" class="btn btn-outline-primary btn-sm w-100 rounded-xl fw-bold" wire:loading.attr="disabled">
                                        <i class="bi bi-download me-1"></i> UNDUH TEMPLATE
                                    </button>
                                </div>
                            </form>
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
                                        <button wire:click="show('{{ $p->id }}')" class="btn btn-outline-info" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
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

            <div class="mt-4">
                {{ $pegawaiList->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL PEGAWAI FORM & DETAIL --}}
    <div wire:ignore.self class="modal fade" id="modalPegawaiForm" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-2xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="modal-header bg-gradient-to-r from-sky-600 via-cyan-600 to-emerald-600 text-white rounded-t-2xl p-4 border-0">
                    <h5 class="modal-title font-display font-bold text-white flex items-center gap-2 text-base">
                        @if ($action == 'edit')
                            <i class="bi bi-pencil-square text-amber-300"></i> Edit Data Pegawai
                        @elseif ($action == 'create')
                            <i class="bi bi-plus-circle text-cyan-200"></i> Tambah Data Pegawai
                        @else
                            <i class="bi bi-person-vcard text-emerald-200"></i> Detail Data Pegawai
                        @endif
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" wire:click="resetForm"></button>
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

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Lembaga Induk / Satmikal Utama <span class="text-danger">*</span></label>
                                <select wire:model="form_lembaga_id" class="form-select" required>
                                    <option value="">-- Pilih Lembaga --</option>
                                    @foreach ($daftarLembaga as $lid => $lname)
                                        <option value="{{ $lid }}">{{ $lname }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Lembaga tugas utama. Penugasan di lembaga lainnya dapat dikelola lewat <strong>SK Penugasan</strong>.</div>
                                @error('form_lembaga_id') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jabatan / Tugas</label>
                                <select wire:model="form_jabatan" class="form-select">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach ($daftarJabatan as $jid => $jname)
                                        <option value="{{ $jid }}">{{ $jname }}</option>
                                    @endforeach
                                </select>
                                @error('form_jabatan') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Nomor SK Penugasan (Awal)</label>
                                <input wire:model="form_nomor_sk" class="form-control" placeholder="Nomor Surat Keputusan">
                                @error('form_nomor_sk') <span class="text-danger small">{{ $message }}</span> @enderror
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
                                    <div class="col-12 mt-3">
                                        <div class="card shadow-none border">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                                <span class="fw-bold small text-primary"><i class="bi bi-file-earmark-medical me-1"></i> Riwayat Lembaga Bertugas & SK Penugasan</span>
                                                <button type="button" class="btn btn-primary btn-sm py-0 px-2" wire:click="kelolaSk('{{ $pData->id }}')" data-bs-dismiss="modal">
                                                    <i class="bi bi-gear me-1"></i> Kelola SK Penugasan
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Lembaga</th>
                                                            <th>Jabatan / Tugas</th>
                                                            <th>No. SK & Tanggal</th>
                                                            <th>Masa Tugas</th>
                                                            <th class="text-center">Status</th>
                                                            <th class="text-center">Berkas</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse ($pData->skPenugasan as $sk)
                                                            <tr>
                                                                <td>
                                                                    <span class="fw-semibold">{{ nama_lembaga($sk->lembaga_id) }}</span>
                                                                    @if ($sk->is_induk)
                                                                        <span class="badge bg-primary ms-1"><i class="bi bi-star-fill text-warning me-1"></i>Induk</span>
                                                                    @else
                                                                        <span class="badge bg-secondary ms-1">Tambahan</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $sk->nama_jabatan ?: '-' }}</td>
                                                                <td>
                                                                    <div>{{ $sk->nomor_sk ?: '-' }}</div>
                                                                    @if ($sk->tanggal_sk)
                                                                        <small class="text-muted">Tgl: {{ $sk->tanggal_sk->format('d/m/Y') }}</small>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div>TMT: {{ $sk->tmt_tugas ? $sk->tmt_tugas->format('d/m/Y') : '-' }}</div>
                                                                    @if ($sk->tmt_selesai)
                                                                        <small class="text-muted">s/d {{ $sk->tmt_selesai->format('d/m/Y') }}</small>
                                                                    @else
                                                                        <small class="text-success">s/d Sekarang</small>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge {{ $sk->status == '1' ? 'bg-success' : 'bg-secondary' }}">
                                                                        {{ $sk->status_label }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    @if ($sk->file_sk && Storage::disk('public')->exists($sk->file_sk))
                                                                        <a href="{{ asset('storage/' . $sk->file_sk) }}" target="_blank" class="btn btn-outline-danger btn-sm py-0 px-2" title="Unduh / Lihat Dokumen SK">
                                                                            <i class="bi bi-file-earmark-pdf"></i> Unduh
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted small">-</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="6" class="text-center text-muted py-2">Belum ada penugasan tercatat</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
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
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-warning btn-sm" wire:click="edit('{{ $pData->id }}')">
                                            <i class="bi bi-pencil me-1"></i> Edit Pegawai
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="kelolaSk('{{ $pData->id }}')" data-bs-dismiss="modal">
                                            <i class="bi bi-file-earmark-medical me-1"></i> Kelola SK Penugasan
                                        </button>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" wire:click="resetForm">Tutup</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL KELOLA SK PENUGASAN --}}
    <div wire:ignore.self class="modal fade" id="modalSkPenugasan" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-2xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="modal-header bg-gradient-to-r from-sky-600 via-cyan-600 to-emerald-600 text-white rounded-t-2xl p-4 border-0">
                    <h5 class="modal-title font-display font-bold text-white flex items-center gap-2 text-base">
                        <i class="bi bi-file-earmark-medical text-cyan-200"></i> Kelola SK Penugasan Lembaga
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" wire:click="resetSkForm"></button>
                </div>
                <div class="modal-body p-4">
                    @if ($selectedPegawai)
                        {{-- PROFIL PEGAWAI SINGKAT --}}
                        <div class="d-flex align-items-center justify-content-between p-3.5 rounded-2xl mb-4 border border-slate-200/80 dark:border-cyan-500/20 bg-slate-50/80 dark:bg-slate-800/50 shadow-inner">
                            <div class="d-flex align-items-center gap-3">
                                @if ($selectedPegawai->foto && Storage::disk('public')->exists($selectedPegawai->foto))
                                    <img src="{{ asset('storage/' . $selectedPegawai->foto) }}" class="rounded-circle object-fit-cover shadow-sm" style="width: 50px; height: 50px;">
                                @else
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white {{ strtolower($selectedPegawai->jenis_kelamin) === 'p' ? 'bg-danger' : 'bg-primary' }}" style="width: 50px; height: 50px;">
                                        <i class="bi {{ strtolower($selectedPegawai->jenis_kelamin) === 'p' ? 'bi-gender-female' : 'bi-gender-male' }} fs-4"></i>
                                    </div>
                                @endif
                                <div>
                                    <h5 class="mb-0 fw-bold">{!! setNamaPegawai($selectedPegawai->nama, $selectedPegawai->gelar_depan, $selectedPegawai->gelar_belakang) !!}</h5>
                                    <div class="small text-muted d-flex gap-2 align-items-center mt-1 flex-wrap">
                                        <span>NIY: <strong>{{ $selectedPegawai->niy ?: '-' }}</strong></span>
                                        <span>•</span>
                                        <span>Lembaga Induk: <strong class="text-primary">{{ nama_lembaga($selectedPegawai->lembaga_id) }}</strong></span>
                                        <span>•</span>
                                        <span>Status: <span class="badge bg-success">{{ $selectedPegawai->status_label }}</span></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                    <i class="bi bi-buildings me-1 text-primary"></i> {{ $selectedPegawai->skPenugasan->count() }} Penugasan Terdaftar
                                </span>
                            </div>
                        </div>

                        {{-- DAFTAR SK PENUGASAN SAAT INI --}}
                        <div class="card shadow-sm border mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold"><i class="bi bi-list-check me-1 text-primary"></i> Riwayat Lembaga Bertugas & SK Penugasan</span>
                                <small class="text-muted">1 Pegawai dapat bertugas di lebih dari 1 lembaga</small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Lembaga</th>
                                            <th>Jabatan / Tugas</th>
                                            <th>Nomor SK & Tgl</th>
                                            <th>Masa Penugasan</th>
                                            <th class="text-center">Induk</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Dokumen</th>
                                            <th class="text-center" style="width: 140px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedPegawai->skPenugasan as $skItem)
                                            <tr class="{{ $sk_id === $skItem->id ? 'table-warning' : '' }}">
                                                <td>
                                                    <div class="fw-bold">{{ nama_lembaga($skItem->lembaga_id) }}</div>
                                                    <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.7rem;">
                                                        {{ nama_lembaga($skItem->lembaga_id, true) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold">{{ $skItem->nama_jabatan ?: '-' }}</span>
                                                    @if ($skItem->keterangan)
                                                        <div class="text-muted small" style="font-size: 0.75rem;">{{ $skItem->keterangan }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($skItem->nomor_sk)
                                                        <div class="font-monospace fw-semibold" style="font-size: 0.85rem;">{{ $skItem->nomor_sk }}</div>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                    @if ($skItem->tanggal_sk)
                                                        <small class="text-muted d-block" style="font-size: 0.72rem;">SK: {{ $skItem->tanggal_sk->format('d/m/Y') }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>TMT: {{ $skItem->tmt_tugas ? $skItem->tmt_tugas->format('d/m/Y') : '-' }}</div>
                                                    @if ($skItem->tmt_selesai)
                                                        <small class="text-muted">s/d {{ $skItem->tmt_selesai->format('d/m/Y') }}</small>
                                                    @else
                                                        <small class="text-success">s/d Sekarang</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($skItem->is_induk)
                                                        <span class="badge bg-primary d-inline-flex align-items-center gap-1 shadow-sm px-2 py-1">
                                                            <i class="bi bi-star-fill text-warning"></i> Lembaga Induk
                                                        </span>
                                                    @else
                                                        <button type="button" wire:click="setIndukSk('{{ $skItem->id }}')" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Jadikan sebagai Lembaga Induk">
                                                            <i class="bi bi-star me-1"></i> Jadikan Induk
                                                        </button>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($skItem->status == '1')
                                                        <span class="badge bg-success">Aktif</span>
                                                    @else
                                                        <span class="badge bg-secondary">Selesai</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($skItem->file_sk && Storage::disk('public')->exists($skItem->file_sk))
                                                        <a href="{{ asset('storage/' . $skItem->file_sk) }}" target="_blank" class="btn btn-outline-danger btn-sm py-0 px-2" title="Lihat / Download File SK">
                                                            <i class="bi bi-file-earmark-pdf"></i> Unduh
                                                        </a>
                                                    @else
                                                        <span class="text-muted small">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" wire:click="editSk('{{ $skItem->id }}')" class="btn btn-outline-warning" title="Edit SK Penugasan">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" wire:click="deleteSk('{{ $skItem->id }}')" wire:confirm="Hapus penugasan di lembaga ini?" class="btn btn-outline-danger" title="Hapus SK Penugasan">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">
                                                    <i class="bi bi-building-exclamation fs-3 d-block mb-1"></i>
                                                    Belum ada SK penugasan lembaga untuk pegawai ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- FORM INPUT / EDIT SK PENUGASAN --}}
                        <div class="card border {{ $isEditingSk ? 'border-warning shadow' : 'border-primary shadow-sm' }}">
                            <div class="card-header {{ $isEditingSk ? 'bg-warning bg-opacity-25 text-dark' : 'bg-primary bg-opacity-10 text-primary' }} d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold">
                                    <i class="bi {{ $isEditingSk ? 'bi-pencil-square' : 'bi-plus-circle' }} me-1"></i>
                                    {{ $isEditingSk ? 'Edit SK Penugasan Lembaga' : 'Tambah SK Penugasan / Penugasan di Lembaga Lain' }}
                                </span>
                                @if ($isEditingSk)
                                    <button type="button" wire:click="cancelEditSk" class="btn btn-outline-secondary btn-sm py-0 px-2">
                                        <i class="bi bi-x me-1"></i> Batal Edit (Tambah Baru)
                                    </button>
                                @endif
                            </div>
                            <div class="card-body p-3">
                                <form wire:submit.prevent="saveSk" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Lembaga Penugasan <span class="text-danger">*</span></label>
                                        <select wire:model="sk_lembaga_id" class="form-select" required>
                                            <option value="">-- Pilih Lembaga --</option>
                                            @foreach ($daftarLembaga as $lid => $lname)
                                                <option value="{{ $lid }}">{{ $lname }}</option>
                                            @endforeach
                                        </select>
                                        @error('sk_lembaga_id') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Jabatan / Tugas</label>
                                        <select wire:model="sk_jabatan" class="form-select">
                                            <option value="">-- Pilih Jabatan --</option>
                                            @foreach ($daftarJabatan as $jid => $jname)
                                                <option value="{{ $jid }}">{{ $jname }}</option>
                                            @endforeach
                                        </select>
                                        @error('sk_jabatan') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Nomor SK Penugasan</label>
                                        <input wire:model="sk_nomor" class="form-control" placeholder="Nomor Surat Keputusan">
                                        @error('sk_nomor') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tanggal SK Ditetapkan</label>
                                        <input type="date" wire:model="sk_tanggal" class="form-control">
                                        @error('sk_tanggal') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">TMT Tugas (Mulai)</label>
                                        <input type="date" wire:model="sk_tmt_tugas" class="form-control">
                                        @error('sk_tmt_tugas') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">TMT Selesai (Opsional)</label>
                                        <input type="date" wire:model="sk_tmt_selesai" class="form-control">
                                        <div class="form-text" style="font-size: 0.72rem;">Kosongkan jika masih aktif / tetap</div>
                                        @error('sk_tmt_selesai') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Status Penugasan <span class="text-danger">*</span></label>
                                        <select wire:model="sk_status" class="form-select" required>
                                            <option value="1">Aktif</option>
                                            <option value="0">Non-Aktif / Selesai</option>
                                        </select>
                                        @error('sk_status') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Unggah Dokumen SK (PDF / Gambar, Max 5MB)</label>
                                        <input type="file" wire:model="sk_file" class="form-control" accept=".pdf,image/*">
                                        <div wire:loading wire:target="sk_file" class="small text-primary mt-1">
                                            <span class="spinner-border spinner-border-sm me-1"></span> Mengunggah berkas SK...
                                        </div>
                                        @error('sk_file') <span class="text-danger small">{{ $message }}</span> @enderror
                                        @if ($sk_existing_file && Storage::disk('public')->exists($sk_existing_file))
                                            <div class="mt-1 small">
                                                <i class="bi bi-file-earmark-check text-success me-1"></i> File SK tersimpan:
                                                <a href="{{ asset('storage/' . $sk_existing_file) }}" target="_blank" class="text-decoration-none">Lihat Dokumen</a>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Keterangan Tambahan</label>
                                        <input wire:model="sk_keterangan" class="form-control" placeholder="Catatan atau keterangan penugasan">
                                        @error('sk_keterangan') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-12">
                                        <div class="form-check p-2 rounded bg-light border">
                                            <input wire:model="sk_is_induk" class="form-check-input ms-0 me-2" type="checkbox" id="checkIsInduk">
                                            <label class="form-check-label fw-bold small text-primary" for="checkIsInduk">
                                                <i class="bi bi-star-fill text-warning me-1"></i> Jadikan Lembaga ini sebagai Lembaga Induk (Satmikal Utama)
                                            </label>
                                            <div class="form-text ms-4" style="font-size: 0.72rem;">Jika dicentang, status lembaga induk pada penugasan lainnya akan dialihkan ke lembaga ini.</div>
                                        </div>
                                    </div>

                                    <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                                        @if ($isEditingSk)
                                            <button type="button" class="btn btn-secondary" wire:click="cancelEditSk">Batal Edit</button>
                                            <button type="submit" class="btn btn-warning" wire:loading.attr="disabled">
                                                <i class="bi bi-save me-1"></i> Perbarui SK Penugasan
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                                <i class="bi bi-plus-circle me-1"></i> Tambahkan SK Penugasan
                                            </button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" wire:click="resetSkForm">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL BULK UPDATE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkUpdate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content rounded-2xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="modal-header bg-gradient-to-r from-sky-600 to-cyan-600 text-white rounded-t-2xl p-4 border-0">
                    <h5 class="modal-title font-display font-bold text-white flex items-center gap-2 text-base">
                        <i class="bi bi-pencil-square text-cyan-200"></i> Update Data Massal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-slate-500 dark:text-slate-400 mb-3">
                        Perubahan akan diterapkan pada <strong class="text-sky-600 dark:text-cyan-400 font-bold">{{ count($checkedPegawai) }}</strong> pegawai yang dipilih.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Pilih Lembaga Baru</label>
                        <select wire:model="bulkLembagaId" class="form-select form-select-sm bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($daftarLembaga as $lid => $lname)
                                <option value="{{ $lid }}">{{ $lname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-display">Pilih Status Baru</label>
                        <select wire:model="bulkStatus" class="form-select form-select-sm bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
                            <option value="">-- Tetap (Tidak Berubah) --</option>
                            @foreach ($statusOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="bulkUpdate" class="btn-logo-gradient w-100 py-2.5 rounded-xl font-bold shadow-md flex items-center justify-center gap-2" wire:loading.attr="disabled">
                        <i class="bi bi-check2-circle"></i> SIMPAN PERUBAHAN MASSAL
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL BULK DELETE --}}
    <div wire:ignore.self class="modal fade" id="modalBulkDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content rounded-2xl border border-rose-500/30 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <div class="modal-header bg-gradient-to-r from-rose-600 to-red-700 text-white rounded-t-2xl p-4 border-0">
                    <h5 class="modal-title font-display font-bold text-white flex items-center gap-2 text-base">
                        <i class="bi bi-exclamation-triangle text-amber-300"></i> Konfirmasi Hapus Massal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-slate-700 dark:text-slate-300">Anda yakin ingin menghapus <strong class="text-rose-500">{{ count($checkedPegawai) }}</strong> pegawai yang dipilih?</p>
                    <p class="text-rose-600 dark:text-rose-400 text-xs font-semibold flex items-center gap-1.5 mb-3">
                        <i class="bi bi-info-circle"></i> Tindakan ini permanen dan tidak dapat dibatalkan!
                    </p>

                    <div class="mb-4">
                        <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-400">
                            Ketik karakter berikut untuk konfirmasi: 
                            <strong class="font-mono text-base ms-1 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-900 user-select-none">{{ $randomConfirmString }}</strong>
                        </label>
                        <input type="text" wire:model="confirmText" class="form-control form-control-sm bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100" placeholder="Ketik karakter konfirmasi..." autocomplete="off">
                    </div>

                    <button wire:click="bulkDelete" class="btn btn-danger w-100 py-2.5 rounded-xl font-bold shadow-md shadow-rose-500/20" wire:loading.attr="disabled">
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

            $wire.on('show-modal-sk', () => {
                const el = document.getElementById('modalSkPenugasan');
                if (el) {
                    new bootstrap.Modal(el).show();
                }
            });
        </script>
    @endscript
</div>
