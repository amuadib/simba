@section('title', 'Pengelolaan Surat')

<?php

use App\Models\Surat;
use App\Models\SuratTemplate;
use App\Models\Siswa;
use App\Models\Pegawai;
use App\Models\User;
use Carbon\Carbon;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new class extends \Livewire\Volt\Component {
    use WithPagination, WithFileUploads;

    // View & Tab State
    public string $activeTab = 'surat'; // 'surat' | 'template'
    public bool $showModal = false;
    public bool $showDetailModal = false;
    public bool $showTemplateModal = false;
    public ?int $selectedSuratId = null;

    // Filter & Search
    public string $q = '';
    public string $filter_lembaga = '';
    public string $filter_kode = '';
    public string $filter_jenis = '';
    public string $filter_tahun = '';

    // Surat Form State
    public ?int $editingSuratId = null;
    public string $form_jenis_pembuatan = 'template'; // 'template' | 'upload'
    public int $form_lembaga_id = 1;
    public string $form_kode_surat = 'SKet';
    public string $form_tanggal_surat = '';
    public int $form_nomor_urut = 1;
    public string $form_nomor_surat = '';
    public string $form_perihal = '';
    public string $form_tujuan = '';
    public string $form_keterangan = '';
    public ?int $form_template_id = null;
    public string $form_konten = '';
    public string $form_target_type = 'umum'; // 'siswa' | 'pegawai' | 'umum'
    public ?string $form_target_id = null;
    public $form_file = null;
    public ?string $existing_file_path = null;

    // Target Selection Helper
    public string $target_search = '';
    public string $selected_target_name = '';

    // Template Form State
    public ?int $editingTemplateId = null;
    public string $template_nama = '';
    public string $template_kode_surat = 'SKet';
    public ?int $template_lembaga_id = null;
    public string $template_deskripsi = '';
    public string $template_konten = '';
    public bool $template_is_active = true;
    public $template_word_file = null;

    // Word Import Modal State
    public bool $showImportWordModal = false;
    public $import_file = null;
    public string $import_nama = '';
    public string $import_kode_surat = 'SKet';
    public ?int $import_lembaga_id = null;
    public string $import_deskripsi = '';
    public string $import_preview_html = '';

    public function mount(): void
    {
        $user = auth()->user();
        $allowedLembaga = $user->getLembagaIds();

        if (! $user->isAdmin()) {
            $defaultLembaga = ! empty($allowedLembaga) ? (int) $allowedLembaga[0] : 1;
            $this->filter_lembaga = (string) $defaultLembaga;
            $this->form_lembaga_id = $defaultLembaga;
        } else {
            $this->filter_lembaga = '';
            $this->form_lembaga_id = ! empty($allowedLembaga) ? (int) $allowedLembaga[0] : 1;
        }

        $this->filter_tahun = (string) date('Y');
        $this->form_tanggal_surat = date('Y-m-d');
        $kodeList = daftar_kode_surat();
        $this->form_kode_surat = array_key_exists('SKet', $kodeList) ? 'SKet' : (array_key_first($kodeList) ?: 'SKet');
        $this->refreshNomorSurat();
    }

    public function refreshNomorSurat(bool $resetToNext = false): void
    {
        $oldNomorSurat = $this->form_nomor_surat;

        $urut = $resetToNext ? null : ($this->form_nomor_urut ?: null);
        $meta = Surat::generateNomorSurat(
            $this->form_lembaga_id,
            $this->form_kode_surat,
            $this->form_tanggal_surat ?: now(),
            $urut
        );

        $this->form_nomor_urut = $meta['nomor_urut'];
        $this->form_nomor_surat = $meta['nomor_surat'];

        if (! empty($oldNomorSurat) && ! empty($this->form_konten) && $oldNomorSurat !== $this->form_nomor_surat) {
            $this->form_konten = str_replace($oldNomorSurat, $this->form_nomor_surat, $this->form_konten);
            $this->dispatch('content-updated', content: $this->form_konten);
        }
    }

    public function resetNomorUrutOtomatis(): void
    {
        $this->refreshNomorSurat(true);
        $this->dispatch('toast', message: 'Nomor urut disetel ulang ke urutan otomatis berikutnya.', type: 'info');
        $this->applyTagReplacements();
    }

    public function updatedFormNomorUrut(): void
    {
        $this->refreshNomorSurat(false);
        $this->applyTagReplacements();
    }

    public function updatedFormLembagaId(): void
    {
        $user = auth()->user();
        if (! $user->canAccessLembaga($this->form_lembaga_id)) {
            $allowed = $user->getLembagaIds();
            $this->form_lembaga_id = ! empty($allowed) ? (int) $allowed[0] : 1;
            $this->dispatch('toast', message: 'Anda hanya berhak memilih lembaga yang ditugaskan.', type: 'warning');
        }

        // Lembaga berubah -> ambil nomor urut otomatis untuk lembaga tersebut
        $this->refreshNomorSurat(true);
        $this->resetTarget();
        $this->applyTagReplacements();
    }

    public function updatedFormKodeSurat(): void
    {
        $this->refreshNomorSurat(false);
        $this->applyTagReplacements();
    }

    public function updatedFormTanggalSurat(): void
    {
        $this->refreshNomorSurat(false);
        $this->applyTagReplacements();
    }

    public function applyTemplate(?int $templateId): void
    {
        $this->form_template_id = $templateId;
        if (! $templateId) {
            return;
        }

        $template = SuratTemplate::find($templateId);
        if ($template) {
            $this->form_kode_surat = $template->kode_surat;
            $this->form_konten = $template->konten;
            if (empty($this->form_perihal)) {
                $this->form_perihal = $template->nama;
            }
            $this->refreshNomorSurat();
            $this->applyTagReplacements();
        }
    }

    public function updatedFormTemplateId($templateId): void
    {
        $this->applyTemplate($templateId ? (int) $templateId : null);
    }

    public function openCreateSurat(string $mode = 'template', ?int $templateId = null): void
    {
        $this->resetSuratForm();
        $this->form_jenis_pembuatan = $mode;

        $user = auth()->user();
        $allowed = $user->getLembagaIds();
        $this->form_lembaga_id = ! empty($allowed) ? (int) $allowed[0] : 1;
        $this->form_tanggal_surat = date('Y-m-d');
        
        $kodeList = daftar_kode_surat();
        $this->form_kode_surat = array_key_exists('SKet', $kodeList) ? 'SKet' : (array_key_first($kodeList) ?: 'SKet');
        $this->refreshNomorSurat(true);

        if ($mode === 'template') {
            $targetTemplate = $templateId
                ? SuratTemplate::find($templateId)
                : SuratTemplate::where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('lembaga_id')->orWhere('lembaga_id', $this->form_lembaga_id);
                    })
                    ->first();

            if ($targetTemplate) {
                $this->applyTemplate($targetTemplate->id);
            }
        }

        $this->showModal = true;
    }

    public function editSurat(int $id): void
    {
        $surat = Surat::findOrFail($id);

        if (! auth()->user()->canAccessLembaga($surat->lembaga_id)) {
            $this->dispatch('toast', message: 'Akses ditolak untuk surat lembaga ini.', type: 'error');
            return;
        }

        $this->editingSuratId = $surat->id;
        $this->form_jenis_pembuatan = $surat->jenis_pembuatan;
        $this->form_lembaga_id = $surat->lembaga_id;
        $this->form_kode_surat = $surat->kode_surat;
        $this->form_tanggal_surat = $surat->tanggal_surat ? $surat->tanggal_surat->format('Y-m-d') : date('Y-m-d');
        $this->form_nomor_urut = $surat->nomor_urut;
        $this->form_nomor_surat = $surat->nomor_surat;
        $this->form_perihal = $surat->perihal;
        $this->form_tujuan = $surat->tujuan ?? '';
        $this->form_keterangan = $surat->keterangan ?? '';
        $this->form_template_id = $surat->surat_template_id;
        $this->form_konten = $surat->konten ?? '';
        $this->form_target_type = $surat->target_type ?? 'umum';
        $this->form_target_id = $surat->target_id;
        $this->existing_file_path = $surat->file_path;

        if ($surat->target_type === 'siswa' && $surat->targetSiswa) {
            $this->selected_target_name = $surat->targetSiswa->nama . ' (NISN: ' . ($surat->targetSiswa->nisn ?: '-') . ')';
        } elseif ($surat->target_type === 'pegawai' && $surat->targetPegawai) {
            $this->selected_target_name = $surat->targetPegawai->nama . ' (NIY: ' . ($surat->targetPegawai->niy ?: '-') . ')';
        } else {
            $this->selected_target_name = $surat->tujuan ?: '';
        }

        $this->showModal = true;
    }

    public function viewSuratDetail(int $id): void
    {
        $surat = Surat::findOrFail($id);
        if (! auth()->user()->canAccessLembaga($surat->lembaga_id)) {
            $this->dispatch('toast', message: 'Akses ditolak untuk surat lembaga ini.', type: 'error');
            return;
        }

        $this->selectedSuratId = $id;
        $this->showDetailModal = true;
    }

    public function selectTargetSiswa($siswaId): void
    {
        $siswa = Siswa::with('rombel')->find($siswaId);
        if (! $siswa) {
            return;
        }

        $this->form_target_type = 'siswa';
        $this->form_target_id = (string) $siswa->id;
        $this->selected_target_name = $siswa->nama . ' (NISN: ' . ($siswa->nisn ?: '-') . ')';
        $this->target_search = '';

        if (empty($this->form_tujuan)) {
            $this->form_tujuan = $siswa->nama;
        }

        $this->applyTagReplacements();
        $this->dispatch('toast', message: 'Target Siswa terpilih: ' . $siswa->nama, type: 'info');
    }

    public function selectTargetPegawai($pegawaiId): void
    {
        $pegawai = Pegawai::with('skPenugasan')->find($pegawaiId);
        if (! $pegawai) {
            return;
        }

        $this->form_target_type = 'pegawai';
        $this->form_target_id = (string) $pegawai->id;
        $this->selected_target_name = $pegawai->nama . ' (NIY: ' . ($pegawai->niy ?: '-') . ')';
        $this->target_search = '';

        if (empty($this->form_tujuan)) {
            $this->form_tujuan = $pegawai->nama;
        }

        $this->applyTagReplacements();
        $this->dispatch('toast', message: 'Target Pegawai terpilih: ' . $pegawai->nama, type: 'info');
    }

    public function resetTarget(): void
    {
        $this->form_target_type = 'umum';
        $this->form_target_id = null;
        $this->selected_target_name = '';
        $this->target_search = '';
    }

    public function applyTagReplacements(): void
    {
        if (empty($this->form_konten)) {
            return;
        }

        $items = \App\Models\Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];
        $lembagaInfo = $items[$this->form_lembaga_id] ?? [];

        $formatDate = function ($dateVal) {
            if (empty($dateVal)) {
                return '-';
            }
            if ($dateVal instanceof \Carbon\CarbonInterface) {
                return $dateVal->isoFormat('D MMMM Y');
            }
            try {
                return Carbon::parse($dateVal)->isoFormat('D MMMM Y');
            } catch (\Throwable $e) {
                return (string) $dateVal;
            }
        };

        $tags = [
            'nomor_surat'     => $this->form_nomor_surat,
            'perihal'         => $this->form_perihal,
            'tujuan'          => $this->form_tujuan,
            'tanggal_surat'   => $formatDate($this->form_tanggal_surat),
            'lembaga'         => nama_lembaga($this->form_lembaga_id),
            'alamat_lembaga'  => $lembagaInfo['alamat'] ?? '',
            'kontak_lembaga'  => $lembagaInfo['kontak'] ?? '',
            'email_lembaga'   => $lembagaInfo['email'] ?? '',
            'kepala_lembaga'  => $lembagaInfo['kepala'] ?? kepala_lembaga($this->form_lembaga_id),
            'nip_kepala'      => $lembagaInfo['nip_kepala'] ?? '-',
            'akreditasi'      => $lembagaInfo['akreditasi'] ?? '-',
            'npsn'            => $lembagaInfo['npsn'] ?? '-',
        ];

        if ($this->form_target_type === 'siswa' && $this->form_target_id) {
            $siswa = Siswa::with('rombel')->find($this->form_target_id);
            if ($siswa) {
                $tglLahirStr = $formatDate($siswa->tanggal_lahir);
                $tags['nama']          = $siswa->nama;
                $tags['nik']           = $siswa->nik ?? '-';
                $tags['nisn']          = $siswa->nisn ?? '-';
                $tags['nis']           = $siswa->nis ?? '-';
                $tags['tempat_lahir']  = $siswa->tempat_lahir ?? '-';
                $tags['tanggal_lahir'] = $tglLahirStr;
                $tags['ttl']           = ($siswa->tempat_lahir ? $siswa->tempat_lahir . ', ' : '') . ($tglLahirStr !== '-' ? $tglLahirStr : '');
                $tags['alamat']        = $siswa->alamat ?? '-';
                $tags['rombel']        = $siswa->rombel ? $siswa->rombel->nama : '-';
            }
        } elseif ($this->form_target_type === 'pegawai' && $this->form_target_id) {
            $pegawai = Pegawai::with('skPenugasan')->find($this->form_target_id);
            if ($pegawai) {
                $tglLahirStr = $formatDate($pegawai->tanggal_lahir);
                $tags['nama']          = $pegawai->nama_lengkap ?? $pegawai->nama;
                $tags['nik']           = $pegawai->nik ?? '-';
                $tags['niy']           = $pegawai->niy ?? '-';
                $tags['nuptk']         = $pegawai->nuptk ?? '-';
                $tags['tempat_lahir']  = $pegawai->tempat_lahir ?? '-';
                $tags['tanggal_lahir'] = $tglLahirStr;
                $tags['ttl']           = ($pegawai->tempat_lahir ? $pegawai->tempat_lahir . ', ' : '') . ($tglLahirStr !== '-' ? $tglLahirStr : '');
                $tags['alamat']        = $pegawai->alamat ?? '-';
                $tags['jabatan']       = $pegawai->daftar_nama_lembaga ?: nama_jabatan($pegawai->skPenugasan()->first()?->jabatan);
            }
        }

        $this->form_konten = Surat::renderTags($this->form_konten, $tags);
        $this->dispatch('content-updated', content: $this->form_konten);
    }

    public function saveSurat(bool $andPrint = false): void
    {
        $user = auth()->user();
        if (! $user->canAccessLembaga($this->form_lembaga_id)) {
            $this->dispatch('toast', message: 'Akses ditolak untuk lembaga ini.', type: 'error');
            return;
        }

        $rules = [
            'form_lembaga_id'    => 'required|integer',
            'form_kode_surat'    => 'required|string',
            'form_tanggal_surat' => 'required|date',
            'form_perihal'       => 'required|string|max:255',
            'form_nomor_surat'   => 'required|string|max:100',
        ];

        if ($this->form_jenis_pembuatan === 'template') {
            $rules['form_konten'] = 'required|string|min:10';
        } else {
            if (! $this->editingSuratId && ! $this->existing_file_path) {
                $rules['form_file'] = 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            } else {
                $rules['form_file'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
            }
        }

        $this->validate($rules, [
            'form_perihal.required' => 'Perihal surat wajib diisi.',
            'form_konten.required'  => 'Konten surat tidak boleh kosong.',
            'form_file.required'    => 'File arsip surat wajib diunggah.',
            'form_file.mimes'       => 'Format file harus PDF, Word, atau Gambar (JPG, PNG).',
            'form_file.max'         => 'Ukuran file maksimal 10 MB.',
        ]);

        $filePath = $this->existing_file_path;
        if ($this->form_file) {
            $filePath = $this->form_file->store('surat/arsip', 'public');
        }

        $date = Carbon::parse($this->form_tanggal_surat);
        $singkatan = nama_lembaga($this->form_lembaga_id, true);
        if (empty($singkatan) || $singkatan === '-') {
            $singkatan = 'LEMBAGA';
        }

        $data = [
            'lembaga_id'         => $this->form_lembaga_id,
            'nomor_surat'        => $this->form_nomor_surat,
            'nomor_urut'         => $this->form_nomor_urut,
            'kode_surat'         => $this->form_kode_surat,
            'singkatan_lembaga'  => $singkatan,
            'bulan_romawi'       => bulan_romawi($date->month),
            'tahun'              => (int) $date->year,
            'tanggal_surat'      => $this->form_tanggal_surat,
            'perihal'            => $this->form_perihal,
            'tujuan'             => $this->form_tujuan ?: null,
            'jenis_pembuatan'    => $this->form_jenis_pembuatan,
            'surat_template_id'  => $this->form_template_id,
            'konten'             => $this->form_jenis_pembuatan === 'template' ? $this->form_konten : null,
            'file_path'          => $filePath,
            'target_type'        => $this->form_target_type,
            'target_id'          => $this->form_target_id,
            'keterangan'         => $this->form_keterangan ?: null,
            'user_id'            => $user->id,
        ];

        $surat = Surat::updateOrCreate(
            ['id' => $this->editingSuratId],
            $data
        );

        $this->showModal = false;
        $this->resetSuratForm();

        $this->dispatch('toast', message: 'Surat berhasil disimpan!', type: 'success');

        if ($andPrint && $surat->jenis_pembuatan === 'template') {
            $this->dispatch('open-print-tab', url: route('surat.print', $surat->id));
        }
    }

    public function deleteSurat(int $id): void
    {
        $surat = Surat::findOrFail($id);
        if (! auth()->user()->canAccessLembaga($surat->lembaga_id)) {
            $this->dispatch('toast', message: 'Akses ditolak untuk surat lembaga ini.', type: 'error');
            return;
        }

        if ($surat->file_path && Storage::disk('public')->exists($surat->file_path)) {
            Storage::disk('public')->delete($surat->file_path);
        }

        $surat->delete();
        $this->dispatch('toast', message: 'Surat berhasil dihapus.', type: 'success');
    }

    public function resetSuratForm(): void
    {
        $this->editingSuratId = null;
        $this->form_perihal = '';
        $this->form_tujuan = '';
        $this->form_keterangan = '';
        $this->form_konten = '';
        $this->form_template_id = null;
        $this->form_file = null;
        $this->existing_file_path = null;
        $this->resetTarget();
    }

    // ==========================================
    // TEMPLATE MANAGEMENT METHODS
    // ==========================================
    public function openCreateTemplate(): void
    {
        $this->resetTemplateForm();
        $user = auth()->user();
        if (! $user->isAdmin()) {
            $allowed = $user->getLembagaIds();
            $this->template_lembaga_id = ! empty($allowed) ? (int) $allowed[0] : 1;
        }
        $this->showTemplateModal = true;
    }

    public function editTemplate(int $id): void
    {
        $tmpl = SuratTemplate::findOrFail($id);
        $user = auth()->user();
        if (! $user->isAdmin() && $tmpl->lembaga_id && ! $user->canAccessLembaga($tmpl->lembaga_id)) {
            $this->dispatch('toast', message: 'Akses ditolak untuk template lembaga ini.', type: 'error');
            return;
        }

        $this->editingTemplateId = $tmpl->id;
        $this->template_nama = $tmpl->nama;
        $this->template_kode_surat = $tmpl->kode_surat;
        $this->template_lembaga_id = $tmpl->lembaga_id;
        $this->template_deskripsi = $tmpl->deskripsi ?? '';
        $this->template_konten = $tmpl->konten;
        $this->template_is_active = $tmpl->is_active;

        $this->showTemplateModal = true;
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'template_nama'       => 'required|string|max:255',
            'template_kode_surat' => 'required|string',
            'template_konten'     => 'required|string|min:10',
        ], [
            'template_nama.required'   => 'Nama template wajib diisi.',
            'template_konten.required' => 'Konten template tidak boleh kosong.',
        ]);

        $user = auth()->user();
        $lembagaId = $this->template_lembaga_id ?: null;
        if (! $user->isAdmin() && (! $lembagaId || ! $user->canAccessLembaga($lembagaId))) {
            $allowed = $user->getLembagaIds();
            $lembagaId = ! empty($allowed) ? (int) $allowed[0] : 1;
        }

        SuratTemplate::updateOrCreate(
            ['id' => $this->editingTemplateId],
            [
                'nama'        => $this->template_nama,
                'kode_surat'  => $this->template_kode_surat,
                'lembaga_id'  => $lembagaId,
                'deskripsi'   => $this->template_deskripsi ?: null,
                'konten'      => $this->template_konten,
                'is_active'   => $this->template_is_active,
                'created_by'  => $user->id,
            ]
        );

        $this->showTemplateModal = false;
        $this->resetTemplateForm();
        $this->dispatch('toast', message: 'Template surat berhasil disimpan!', type: 'success');
    }

    public function deleteTemplate(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            $this->dispatch('toast', message: 'Hanya Admin yang dapat menghapus template master.', type: 'error');
            return;
        }

        $tmpl = SuratTemplate::findOrFail($id);
        $tmpl->delete();
        $this->dispatch('toast', message: 'Template surat berhasil dihapus.', type: 'success');
    }

    public function resetTemplateForm(): void
    {
        $this->editingTemplateId = null;
        $this->template_nama = '';
        $kodeList = daftar_kode_surat();
        $this->template_kode_surat = array_key_exists('SKet', $kodeList) ? 'SKet' : (array_key_first($kodeList) ?: 'SKet');
        $this->template_lembaga_id = null;
        $this->template_deskripsi = '';
        $this->template_konten = '';
        $this->template_is_active = true;
        $this->template_word_file = null;
    }

    public function openImportWordModal(): void
    {
        $this->import_file = null;
        $this->import_nama = '';
        $kodeList = daftar_kode_surat();
        $this->import_kode_surat = array_key_exists('SKet', $kodeList) ? 'SKet' : (array_key_first($kodeList) ?: 'SKet');
        
        $user = auth()->user();
        if (! $user->isAdmin()) {
            $allowed = $user->getLembagaIds();
            $this->import_lembaga_id = ! empty($allowed) ? (int) $allowed[0] : 1;
        } else {
            $this->import_lembaga_id = null;
        }

        $this->import_deskripsi = '';
        $this->import_preview_html = '';
        $this->showImportWordModal = true;
    }

    public function updatedImportFile(): void
    {
        $this->validate([
            'import_file' => 'required|file|mimes:docx|max:10240',
        ], [
            'import_file.mimes' => 'Format file harus berupa dokumen Word (.docx).',
            'import_file.max'   => 'Ukuran file Word maksimal 10 MB.',
        ]);

        $tempPath = $this->import_file->getRealPath();
        $extractedHtml = \App\Services\DocxConverterService::convertToHtml($tempPath);

        if (empty($extractedHtml)) {
            $this->dispatch('toast', message: 'Gagal mengekstrak isi dokumen Word atau berkas kosong.', type: 'error');
            return;
        }

        $this->import_preview_html = $extractedHtml;

        if (empty($this->import_nama)) {
            $cleanName = pathinfo($this->import_file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = str_replace(['_', '-'], ' ', $cleanName);
            $this->import_nama = ucwords($cleanName);
        }

        $this->dispatch('toast', message: 'Dokumen Word berhasil dibaca!', type: 'success');
    }

    public function saveImportedWordTemplate(bool $openInEditor = false): void
    {
        $this->validate([
            'import_nama'         => 'required|string|max:255',
            'import_kode_surat'   => 'required|string',
            'import_preview_html' => 'required|string|min:5',
        ], [
            'import_nama.required'         => 'Nama template wajib diisi.',
            'import_preview_html.required' => 'Belum ada konten Word yang diekstrak.',
        ]);

        $user = auth()->user();
        $lembagaId = $this->import_lembaga_id ?: null;
        if (! $user->isAdmin() && (! $lembagaId || ! $user->canAccessLembaga($lembagaId))) {
            $allowed = $user->getLembagaIds();
            $lembagaId = ! empty($allowed) ? (int) $allowed[0] : 1;
        }

        if ($openInEditor) {
            $this->showImportWordModal = false;
            $this->editingTemplateId = null;
            $this->template_nama = $this->import_nama;
            $this->template_kode_surat = $this->import_kode_surat;
            $this->template_lembaga_id = $lembagaId;
            $this->template_deskripsi = $this->import_deskripsi;
            $this->template_konten = $this->import_preview_html;
            $this->template_is_active = true;
            $this->showTemplateModal = true;
            $this->dispatch('template-content-updated', content: $this->template_konten);
            return;
        }

        SuratTemplate::create([
            'nama'        => $this->import_nama,
            'kode_surat'  => $this->import_kode_surat,
            'lembaga_id'  => $lembagaId,
            'deskripsi'   => $this->import_deskripsi ?: null,
            'konten'      => $this->import_preview_html,
            'is_active'   => true,
            'created_by'  => $user->id,
        ]);

        $this->showImportWordModal = false;
        $this->dispatch('toast', message: 'Template dari file Word berhasil disimpan!', type: 'success');
    }

    public function updatedTemplateWordFile(): void
    {
        $this->validate([
            'template_word_file' => 'required|file|mimes:docx|max:10240',
        ], [
            'template_word_file.mimes' => 'Format file harus berupa dokumen Word (.docx).',
            'template_word_file.max'   => 'Ukuran file Word maksimal 10 MB.',
        ]);

        $tempPath = $this->template_word_file->getRealPath();
        $extractedHtml = \App\Services\DocxConverterService::convertToHtml($tempPath);

        if (empty($extractedHtml)) {
            $this->dispatch('toast', message: 'Gagal mengekstrak isi dokumen Word atau berkas kosong.', type: 'error');
            return;
        }

        $this->template_konten = $extractedHtml;

        if (empty($this->template_nama)) {
            $cleanName = pathinfo($this->template_word_file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = str_replace(['_', '-'], ' ', $cleanName);
            $this->template_nama = ucwords($cleanName);
        }

        $this->dispatch('template-content-updated', content: $this->template_konten);
        $this->dispatch('toast', message: 'Dokumen Word berhasil dimasukkan ke editor template!', type: 'success');
    }

    public function with(): array
    {
        $user = auth()->user();
        $allowedLembaga = $user->getLembagaIds();

        // 1. SURATS QUERY
        $suratQuery = Surat::query()
            ->with(['user', 'template', 'targetSiswa', 'targetPegawai'])
            ->latest('tanggal_surat')
            ->latest('id');

        // Scoping for non-admin
        if (! $user->isAdmin()) {
            $suratQuery->whereIn('lembaga_id', $allowedLembaga);
        }

        if ($this->filter_lembaga !== '') {
            $suratQuery->where('lembaga_id', (int) $this->filter_lembaga);
        }

        if ($this->filter_kode !== '') {
            $suratQuery->where('kode_surat', $this->filter_kode);
        }

        if ($this->filter_jenis !== '') {
            $suratQuery->where('jenis_pembuatan', $this->filter_jenis);
        }

        if ($this->filter_tahun !== '') {
            $suratQuery->where('tahun', (int) $this->filter_tahun);
        }

        if (! empty($this->q)) {
            $search = $this->q;
            $suratQuery->where(function ($sub) use ($search) {
                $sub->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('perihal', 'like', "%{$search}%")
                    ->orWhere('tujuan', 'like', "%{$search}%")
                    ->orWhereHas('targetSiswa', fn($sq) => $sq->where('nama', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%"))
                    ->orWhereHas('targetPegawai', fn($pq) => $pq->where('nama', 'like', "%{$search}%")->orWhere('niy', 'like', "%{$search}%"));
            });
        }

        $surats = $suratQuery->paginate(15);

        // 2. TEMPLATES QUERY
        $templateQuery = SuratTemplate::query()->with('user')->latest('id');
        if (! $user->isAdmin()) {
            $templateQuery->where(function ($q) use ($allowedLembaga) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $allowedLembaga);
            });
        }
        $templates = $templateQuery->get();

        // 3. TARGET SEARCH RESULTS
        $targetResults = [];
        if (! empty($this->target_search) && strlen($this->target_search) >= 2) {
            $search = $this->target_search;
            if ($this->form_target_type === 'siswa') {
                $targetResults = Siswa::query()
                    ->where('lembaga_id', $this->form_lembaga_id)
                    ->where(function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                          ->orWhere('nisn', 'like', "%{$search}%")
                          ->orWhere('nis', 'like', "%{$search}%");
                    })
                    ->limit(8)
                    ->get();
            } elseif ($this->form_target_type === 'pegawai') {
                $targetResults = Pegawai::query()
                    ->where(function ($q) {
                        $q->where('lembaga_id', $this->form_lembaga_id)
                          ->orWhereHas('skPenugasan', fn($sk) => $sk->where('lembaga_id', $this->form_lembaga_id));
                    })
                    ->where(function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                          ->orWhere('niy', 'like', "%{$search}%")
                          ->orWhere('nuptk', 'like', "%{$search}%");
                    })
                    ->limit(8)
                    ->get();
            }
        }

        // 4. STATS
        $statsQuery = Surat::query();
        if (! $user->isAdmin()) {
            $statsQuery->whereIn('lembaga_id', $allowedLembaga);
        }
        $totalSurat = (clone $statsQuery)->count();
        $totalTemplate = (clone $statsQuery)->where('jenis_pembuatan', 'template')->count();
        $totalUpload = (clone $statsQuery)->where('jenis_pembuatan', 'upload')->count();

        // Active viewing letter detail
        $viewingSurat = $this->selectedSuratId ? Surat::with(['template', 'user', 'targetSiswa', 'targetPegawai'])->find($this->selectedSuratId) : null;

        return compact('surats', 'templates', 'targetResults', 'totalSurat', 'totalTemplate', 'totalUpload', 'viewingSurat', 'allowedLembaga');
    }
};
?>

<div class="space-y-6" x-data="{
    insertTag(tag) {
        const editor = document.getElementById('wysiwyg-editor');
        if (!editor) return;
        editor.focus();
        document.execCommand('insertText', false, tag);
        $wire.set('form_konten', editor.innerHTML);
    },
    formatDoc(cmd, value = null) {
        document.execCommand(cmd, false, value);
        const editor = document.getElementById('wysiwyg-editor');
        if (editor) $wire.set('form_konten', editor.innerHTML);
    },
    insertTemplateTag(tag) {
        const editor = document.getElementById('wysiwyg-template-editor');
        if (!editor) return;
        editor.focus();
        document.execCommand('insertText', false, tag);
        $wire.set('template_konten', editor.innerHTML);
    },
    formatTemplateDoc(cmd, value = null) {
        document.execCommand(cmd, false, value);
        const editor = document.getElementById('wysiwyg-template-editor');
        if (editor) $wire.set('template_konten', editor.innerHTML);
    }
}"
x-init="
    window.addEventListener('open-print-tab', event => {
        window.open(event.detail.url, '_blank');
    });
    window.addEventListener('content-updated', event => {
        const editor = document.getElementById('wysiwyg-editor');
        if (editor) editor.innerHTML = event.detail.content;
    });
    window.addEventListener('template-content-updated', event => {
        const editor = document.getElementById('wysiwyg-template-editor');
        if (editor) editor.innerHTML = event.detail.content;
    });
">

    {{-- HEADER / BREADCRUMB --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-3xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-200/40 dark:shadow-none">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 mb-1">
                <i class="bi bi-envelope-paper-fill"></i>
                <span>Administrasi Persuratan</span>
            </div>
            <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 font-display flex items-center gap-2">
                Pengelolaan Surat Resmi
                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20">
                    Format Standar & Arsip
                </span>
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Format nomor: <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-sky-600 dark:text-sky-400 font-mono text-[11px]">&lt;urut&gt;/&lt;kode&gt;/&lt;lembaga&gt;/&lt;romawi&gt;/&lt;tahun&gt;</code>
            </p>
        </div>

        {{-- ACTION BUTTONS --}}
        <div class="flex items-center flex-wrap gap-2">
            <button type="button" 
                    wire:click="openCreateSurat('template')"
                    class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500 hover:from-sky-600 hover:to-emerald-600 text-white font-semibold text-xs shadow-lg shadow-cyan-500/30 flex items-center gap-2 transition-all transform active:scale-95">
                <i class="bi bi-file-earmark-plus-fill text-sm"></i>
                <span>Buat Surat (Template)</span>
            </button>

            <button type="button" 
                    wire:click="openCreateSurat('upload')"
                    class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 font-semibold text-xs shadow-sm flex items-center gap-2 transition-all border border-slate-700/50">
                <i class="bi bi-cloud-arrow-up-fill text-sm text-cyan-400"></i>
                <span>Upload Arsip Fisik</span>
            </button>

            <button type="button" 
                    wire:click="$set('activeTab', 'template')"
                    :class="$wire.activeTab === 'template' ? 'bg-cyan-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200'"
                    class="px-3.5 py-2.5 rounded-2xl font-semibold text-xs flex items-center gap-2 transition-all border border-slate-200 dark:border-slate-700">
                <i class="bi bi-layout-text-window-reverse text-sm"></i>
                <span>Editor Template</span>
            </button>
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-sky-500/10 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl">
                <i class="bi bi-envelope-paper"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $totalSurat }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Surat Tercatat</div>
            </div>
        </div>

        <div class="p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 dark:bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 flex items-center justify-center text-xl">
                <i class="bi bi-file-earmark-richtext"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $totalTemplate }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Dibuat via Template</div>
            </div>
        </div>

        <div class="p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                <i class="bi bi-cloud-check"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $totalUpload }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Arsip File Terunggah</div>
            </div>
        </div>

        <div class="p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-violet-500/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl">
                <i class="bi bi-building-check"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">
                    {{ auth()->user()->isAdmin() ? 'Semua Lembaga (Admin)' : (count($allowedLembaga) > 0 ? nama_lembaga($allowedLembaga[0], true) : 'Pegawai') }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Otoritas Akses Lembaga</div>
            </div>
        </div>
    </div>

    {{-- TABS NAVIGATION --}}
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <button type="button" 
                wire:click="$set('activeTab', 'surat')"
                class="px-5 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 {{ $activeTab === 'surat' ? 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
            <i class="bi bi-collection"></i>
            <span>Daftar Arsip Surat Keluar</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-800 font-extrabold">{{ $totalSurat }}</span>
        </button>

        <button type="button" 
                wire:click="$set('activeTab', 'template')"
                class="px-5 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 {{ $activeTab === 'template' ? 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
            <i class="bi bi-layout-text-window"></i>
            <span>Kelola Template WYSIWYG</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-800 font-extrabold">{{ count($templates) }}</span>
        </button>
    </div>

    {{-- TAB 1: DAFTAR ARSIP SURAT --}}
    @if ($activeTab === 'surat')
        <div class="space-y-4">
            {{-- FILTER BAR --}}
            <div class="p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 flex-1">
                    {{-- Search --}}
                    <div class="relative">
                        <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" 
                               wire:model.live.debounce.300ms="q" 
                               placeholder="Cari nomor, perihal, nama..." 
                               class="w-full pl-9 pr-3 py-2 rounded-2xl text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    </div>

                    {{-- Lembaga Filter --}}
                    <div>
                        <select wire:model.live="filter_lembaga" 
                                class="w-full px-3 py-2 rounded-2xl text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            @if (auth()->user()->isAdmin())
                                <option value="">Semua Lembaga</option>
                                @foreach (daftar_lembaga() as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            @else
                                @foreach ($allowedLembaga as $id)
                                    <option value="{{ $id }}">{{ nama_lembaga($id) }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{-- Kode Surat Filter --}}
                    <div>
                        <select wire:model.live="filter_kode" 
                                class="w-full px-3 py-2 rounded-2xl text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <option value="">Semua Kode Surat</option>
                            @foreach (daftar_kode_surat() as $kode => $label)
                                <option value="{{ $kode }}">{{ $kode }} - {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Jenis Pembuatan Filter --}}
                    <div>
                        <select wire:model.live="filter_jenis" 
                                class="w-full px-3 py-2 rounded-2xl text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <option value="">Semua Format Pembuatan</option>
                            <option value="template">Template Resmi</option>
                            <option value="upload">Upload Arsip Fisik</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <select wire:model.live="filter_tahun" 
                            class="px-3 py-2 rounded-2xl text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        <option value="">Semua Tahun</option>
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>

                    @if ($q || $filter_kode || $filter_jenis || (!auth()->user()->isAdmin() ? false : $filter_lembaga))
                        <button type="button" 
                                wire:click="$set('q', ''); $set('filter_kode', ''); $set('filter_jenis', ''); @if(auth()->user()->isAdmin()) $set('filter_lembaga', ''); @endif"
                                class="p-2 rounded-2xl bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 text-xs font-semibold transition-colors"
                                title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    @endif
                </div>
            </div>

            {{-- SURAT TABLE --}}
            <div class="rounded-3xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-200/30 dark:shadow-none overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3.5 px-4 w-12 text-center">No</th>
                                <th class="py-3.5 px-4">Nomor Surat</th>
                                <th class="py-3.5 px-4">Tanggal</th>
                                <th class="py-3.5 px-4">Lembaga</th>
                                <th class="py-3.5 px-4">Perihal / Tujuan</th>
                                <th class="py-3.5 px-4">Target Terkait</th>
                                <th class="py-3.5 px-4 text-center">Metode</th>
                                <th class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            @forelse ($surats as $index => $surat)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3.5 px-4 text-center text-slate-400 font-medium">
                                        {{ $surats->firstItem() + $index }}
                                    </td>

                                    {{-- Nomor Surat --}}
                                    <td class="py-3.5 px-4">
                                        <div class="font-mono font-bold text-slate-800 dark:text-slate-100 text-xs flex items-center gap-1.5">
                                            <i class="bi bi-hash text-cyan-500"></i>
                                            <span>{{ $surat->nomor_surat }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 mt-1">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                                {{ $surat->kode_surat }} - {{ $surat->nama_kode_surat }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Tanggal --}}
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-700 dark:text-slate-200">
                                            {{ $surat->tanggal_surat ? $surat->tanggal_surat->isoFormat('D MMM Y') : '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            Bulan Romawi: {{ $surat->bulan_romawi }}
                                        </div>
                                    </td>

                                    {{-- Lembaga --}}
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                            {{ $surat->singkatan_lembaga }}
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[140px]" title="{{ $surat->nama_lembaga }}">
                                            {{ $surat->nama_lembaga }}
                                        </div>
                                    </td>

                                    {{-- Perihal & Tujuan --}}
                                    <td class="py-3.5 px-4 min-w-[200px]">
                                        <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                            {{ $surat->perihal }}
                                        </div>
                                        @if ($surat->tujuan)
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                                <i class="bi bi-arrow-return-right text-[10px]"></i>
                                                <span>Yth: {{ $surat->tujuan }}</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Target Terkait --}}
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        @if ($surat->target_type === 'siswa' && $surat->targetSiswa)
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-6 h-6 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xs">
                                                    <i class="bi bi-person"></i>
                                                </span>
                                                <div>
                                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $surat->targetSiswa->nama }}</div>
                                                    <div class="text-[10px] text-slate-400">NISN: {{ $surat->targetSiswa->nisn ?: '-' }}</div>
                                                </div>
                                            </div>
                                        @elseif ($surat->target_type === 'pegawai' && $surat->targetPegawai)
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">
                                                    <i class="bi bi-person-badge"></i>
                                                </span>
                                                <div>
                                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $surat->targetPegawai->nama }}</div>
                                                    <div class="text-[10px] text-slate-400">NIY: {{ $surat->targetPegawai->niy ?: '-' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-slate-400 italic text-[11px]">Umum / Non-Spesifik</span>
                                        @endif
                                    </td>

                                    {{-- Metode --}}
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        @if ($surat->jenis_pembuatan === 'template')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20">
                                                <i class="bi bi-file-earmark-richtext"></i>
                                                <span>Template</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                                <span>Arsip Upload</span>
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            {{-- Print / View Document --}}
                                            @if ($surat->jenis_pembuatan === 'template')
                                                <a href="{{ route('surat.print', $surat->id) }}" 
                                                   target="_blank"
                                                   class="p-2 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 transition-colors"
                                                   title="Cetak Surat Resmi">
                                                    <i class="bi bi-printer text-sm"></i>
                                                </a>
                                            @elseif ($surat->file_path)
                                                <a href="{{ route('surat.download', $surat->id) }}" 
                                                   target="_blank"
                                                   class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 transition-colors"
                                                   title="Unduh Berkas Arsip">
                                                    <i class="bi bi-download text-sm"></i>
                                                </a>
                                            @endif

                                            {{-- Detail Quick View --}}
                                            <button type="button" 
                                                    wire:click="viewSuratDetail({{ $surat->id }})"
                                                    class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                                                    title="Lihat Rincian">
                                                <i class="bi bi-eye text-sm"></i>
                                            </button>

                                            {{-- Edit --}}
                                            <button type="button" 
                                                    wire:click="editSurat({{ $surat->id }})"
                                                    class="p-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 transition-colors"
                                                    title="Edit Surat">
                                                <i class="bi bi-pencil-square text-sm"></i>
                                            </button>

                                            {{-- Delete --}}
                                            <button type="button" 
                                                    wire:confirm="Yakin ingin menghapus surat {{ $surat->nomor_surat }}?"
                                                    wire:click="deleteSurat({{ $surat->id }})"
                                                    class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 transition-colors"
                                                    title="Hapus Surat">
                                                <i class="bi bi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-slate-400">
                                        <div class="w-16 h-16 mx-auto mb-3 rounded-3xl bg-slate-100 dark:bg-slate-800/80 flex items-center justify-center text-2xl text-slate-400">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <p class="font-bold text-slate-600 dark:text-slate-300">Belum ada surat yang tercatat</p>
                                        <p class="text-xs text-slate-400 mt-1">Klik tombol Buat Surat atau Upload Arsip untuk menambahkan surat baru.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                @if ($surats->hasPages())
                    <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                        {{ $surats->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 2: KELOLA TEMPLATE WYSIWYG --}}
    @if ($activeTab === 'template')
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 font-display">
                        Daftar Template Surat Master
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Template dengan tag dinamis seperti <code class="text-cyan-500 font-mono">{nama}</code>, <code class="text-cyan-500 font-mono">{nik}</code>, <code class="text-cyan-500 font-mono">{ttl}</code> akan otomatis terisi data siswa / pegawai.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" 
                            wire:click="openImportWordModal"
                            class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold text-xs shadow-md shadow-blue-500/20 flex items-center gap-2 transition-all">
                        <i class="bi bi-file-earmark-word-fill text-sm"></i>
                        <span>Impor dari Word (.docx)</span>
                    </button>

                    <button type="button" 
                            wire:click="openCreateTemplate"
                            class="px-4 py-2.5 rounded-2xl bg-cyan-500 hover:bg-cyan-600 text-white font-semibold text-xs shadow-md shadow-cyan-500/20 flex items-center gap-2 transition-all">
                        <i class="bi bi-plus-lg"></i>
                        <span>Tambah Template Baru</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($templates as $tmpl)
                    <div class="p-5 rounded-3xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-md flex flex-col justify-between hover:border-cyan-500/40 transition-all">
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                    {{ $tmpl->kode_surat }} • {{ $tmpl->nama_kode_surat }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tmpl->is_active ? 'bg-emerald-500/10 text-emerald-500' : 'bg-slate-200 text-slate-500' }}">
                                    {{ $tmpl->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </div>

                            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 mb-1">
                                {{ $tmpl->nama }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-3">
                                {{ $tmpl->deskripsi ?: 'Tidak ada deskripsi' }}
                            </p>

                            <div class="text-[11px] text-slate-400 flex items-center gap-1.5 mb-4">
                                <i class="bi bi-building"></i>
                                <span>{{ $tmpl->nama_lembaga }}</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                            <button type="button" 
                                    wire:click="openCreateSurat('template', {{ $tmpl->id }})"
                                    class="text-xs font-bold text-cyan-600 dark:text-cyan-400 hover:underline flex items-center gap-1">
                                <i class="bi bi-pencil-square"></i> Gunakan Template
                            </button>

                            <div class="flex items-center gap-1">
                                <button type="button" 
                                        wire:click="editTemplate({{ $tmpl->id }})"
                                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 text-xs transition-colors"
                                        title="Edit Template">
                                    <i class="bi bi-gear"></i>
                                </button>
                                @if (auth()->user()->isAdmin())
                                    <button type="button" 
                                            wire:confirm="Yakin ingin menghapus template ini?"
                                            wire:click="deleteTemplate({{ $tmpl->id }})"
                                            class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 text-xs transition-colors"
                                            title="Hapus Template">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400">
                        <i class="bi bi-layout-text-window text-4xl mb-2 block"></i>
                        <p class="font-bold">Belum ada template surat.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ========================================================
         MODAL 1: BUAT / EDIT SURAT (TEMPLATE & UPLOAD)
    ======================================================== --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5"
             x-transition>
            <div class="relative w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-6">
                
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-sky-500/10 via-cyan-500/5 to-transparent">
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-cyan-600 dark:text-cyan-400 font-display">
                            {{ $editingSuratId ? 'Perbarui Data Surat' : 'Penerbitan Surat Resmi Baru' }}
                        </div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 font-display flex items-center gap-2">
                            @if ($form_jenis_pembuatan === 'template')
                                <i class="bi bi-file-earmark-richtext text-cyan-500"></i> Buat Surat via Template WYSIWYG
                            @else
                                <i class="bi bi-cloud-arrow-up text-emerald-500"></i> Unggah Arsip Berkas Fisik
                            @endif
                        </h2>
                    </div>

                    <button type="button" 
                            wire:click="$set('showModal', false)" 
                            class="p-2 rounded-2xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 max-h-[75vh] overflow-y-auto space-y-6">

                    {{-- Form Mode Toggle (if new surat) --}}
                    @if (!$editingSuratId)
                        <div class="flex rounded-2xl bg-slate-100 dark:bg-slate-800/80 p-1 border border-slate-200 dark:border-slate-700/80">
                            <button type="button" 
                                    wire:click="$set('form_jenis_pembuatan', 'template')"
                                    class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $form_jenis_pembuatan === 'template' ? 'bg-white dark:bg-slate-900 text-cyan-600 dark:text-cyan-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
                                <i class="bi bi-file-earmark-text"></i> Gunakan Template Visual
                            </button>
                            <button type="button" 
                                    wire:click="$set('form_jenis_pembuatan', 'upload')"
                                    class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $form_jenis_pembuatan === 'upload' ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
                                <i class="bi bi-cloud-arrow-up"></i> Unggah File Eksternal / Fisik
                            </button>
                        </div>
                    @endif

                    {{-- SECTION 1: HEADER & NOMOR SURAT --}}
                    <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- Lembaga --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Lembaga Penerbit <span class="text-rose-500">*</span>
                                </label>
                                <select wire:model.live="form_lembaga_id" 
                                        {{ !auth()->user()->isAdmin() && count($allowedLembaga) <= 1 ? 'disabled' : '' }}
                                        class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-semibold focus:ring-2 focus:ring-cyan-500">
                                    @if (auth()->user()->isAdmin())
                                        @foreach (daftar_lembaga() as $id => $nama)
                                            <option value="{{ $id }}">{{ $nama }}</option>
                                        @endforeach
                                    @else
                                        @foreach ($allowedLembaga as $id)
                                            <option value="{{ $id }}">{{ nama_lembaga($id) }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @if (!auth()->user()->isAdmin())
                                    <span class="text-[10px] text-slate-400 mt-1 block">Terkunci sesuai hak penugasan Anda.</span>
                                @endif
                            </div>

                            {{-- Kode Surat --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Klasifikasi / Kode Surat <span class="text-rose-500">*</span>
                                </label>
                                <select wire:model.live="form_kode_surat" 
                                        class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-semibold focus:ring-2 focus:ring-cyan-500">
                                    @foreach (daftar_kode_surat() as $kode => $label)
                                        <option value="{{ $kode }}">{{ $kode }} - {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Tanggal Surat --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Tanggal Surat <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" 
                                       wire:model.live="form_tanggal_surat" 
                                       class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                            </div>
                        </div>

                        {{-- DYNAMIC NOMOR SURAT BANNER --}}
                        <div class="p-3 rounded-xl bg-gradient-to-r from-cyan-500/10 via-sky-500/10 to-emerald-500/10 border border-cyan-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Nomor Surat Resmi Dihasilkan Otomatis
                                </div>
                                <div class="font-mono text-base font-black text-cyan-700 dark:text-cyan-300 tracking-wide mt-0.5">
                                    {{ $form_nomor_surat }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                    No. Urut: <input type="number" min="1" wire:model.live.debounce.150ms="form_nomor_urut" class="w-16 px-2 py-1 rounded-lg text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-bold text-center">
                                </div>
                                <button type="button" 
                                        wire:click="resetNomorUrutOtomatis" 
                                        class="p-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-cyan-600 dark:text-cyan-400 hover:bg-slate-50 text-xs"
                                        title="Reset ke Nomor Urut Otomatis Lembaga">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Perihal & Tujuan --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Perihal Surat <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" 
                                       wire:model="form_perihal" 
                                       placeholder="Cth: Surat Keterangan Siswa Aktif" 
                                       class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                                @error('form_perihal') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Tujuan / Penerima Surat
                                </label>
                                <input type="text" 
                                       wire:model="form_tujuan" 
                                       placeholder="Cth: Wali Murid / Nama Instansi Luar" 
                                       class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: TARGET OTOMATIS (SISWA / PEGAWAI) --}}
                    @if ($form_jenis_pembuatan === 'template')
                        <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <i class="bi bi-people-fill text-cyan-500"></i>
                                    <span>Pilih Target Data untuk Tag Otomatis (Opsional)</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <label class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1 cursor-pointer">
                                        <input type="radio" wire:model.live="form_target_type" value="siswa" class="text-cyan-500">
                                        <span>Siswa</span>
                                    </label>
                                    <label class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1 cursor-pointer">
                                        <input type="radio" wire:model.live="form_target_type" value="pegawai" class="text-cyan-500">
                                        <span>Pegawai</span>
                                    </label>
                                    <label class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1 cursor-pointer">
                                        <input type="radio" wire:model.live="form_target_type" value="umum" class="text-cyan-500">
                                        <span>Manual / Umum</span>
                                    </label>
                                </div>
                            </div>

                            @if ($form_target_type !== 'umum')
                                <div class="space-y-2">
                                    <div class="relative">
                                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                        <input type="text" 
                                               wire:model.live.debounce.250ms="target_search" 
                                               placeholder="Ketik minimal 2 huruf nama, NIK, NISN, atau NIY..." 
                                               class="w-full pl-8 pr-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                                    </div>

                                    {{-- Selected Target Pill --}}
                                    @if ($selected_target_name)
                                        <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-between">
                                            <div class="flex items-center gap-2 text-xs">
                                                <i class="bi bi-check-circle-fill text-cyan-500"></i>
                                                <span class="font-bold text-slate-800 dark:text-slate-100">Target Aktif:</span>
                                                <span class="text-cyan-700 dark:text-cyan-300 font-semibold">{{ $selected_target_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" 
                                                        wire:click="applyTagReplacements"
                                                        class="px-2 py-1 rounded-lg bg-cyan-500 text-white font-bold text-[10px] hover:bg-cyan-600 transition-colors">
                                                    Terapkan Tag Ulang
                                                </button>
                                                <button type="button" 
                                                        wire:click="resetTarget" 
                                                        class="p-1 text-slate-400 hover:text-rose-500 text-xs">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Dropdown Target Search Results --}}
                                    @if (!empty($targetResults))
                                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700 shadow-lg max-h-48 overflow-y-auto">
                                            @foreach ($targetResults as $item)
                                                @if ($form_target_type === 'siswa')
                                                    <div wire:click="selectTargetSiswa('{{ $item->id }}')" 
                                                         class="p-2.5 hover:bg-cyan-500/10 cursor-pointer flex items-center justify-between transition-colors">
                                                        <div>
                                                            <div class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ $item->nama }}</div>
                                                            <div class="text-[10px] text-slate-400">NISN: {{ $item->nisn ?: '-' }} • NIK: {{ $item->nik ?: '-' }} • Kelas: {{ $item->rombel?->nama ?: '-' }}</div>
                                                        </div>
                                                        <span class="text-xs text-cyan-600 dark:text-cyan-400 font-semibold">Pilih</span>
                                                    </div>
                                                @else
                                                    <div wire:click="selectTargetPegawai('{{ $item->id }}')" 
                                                         class="p-2.5 hover:bg-cyan-500/10 cursor-pointer flex items-center justify-between transition-colors">
                                                        <div>
                                                            <div class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ $item->nama }}</div>
                                                            <div class="text-[10px] text-slate-400">NIY: {{ $item->niy ?: '-' }} • NIK: {{ $item->nik ?: '-' }} • NUPTK: {{ $item->nuptk ?: '-' }}</div>
                                                        </div>
                                                        <span class="text-xs text-cyan-600 dark:text-cyan-400 font-semibold">Pilih</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- SECTION 3: WYSIWYG EDITOR (FOR TEMPLATE MODE) --}}
                    @if ($form_jenis_pembuatan === 'template')
                        <div class="space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <i class="bi bi-textarea-t text-cyan-500"></i>
                                    <span>Konten Surat (Editor Visual & Tag Dinamis)</span>
                                </label>

                                {{-- Template Selector Dropdown --}}
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-slate-400">Gunakan Master:</span>
                                    <select wire:model.live="form_template_id" 
                                            class="px-2.5 py-1 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-medium">
                                        <option value="">-- Pilih Template --</option>
                                        @foreach ($templates as $tmpl)
                                            <option value="{{ $tmpl->id }}">{{ $tmpl->nama }} ({{ $tmpl->kode_surat }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- TAG INSERTION PALETTE --}}
                            <div class="p-2.5 rounded-2xl bg-cyan-500/5 dark:bg-cyan-500/10 border border-cyan-500/20">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-cyan-700 dark:text-cyan-300 mb-1.5 flex items-center gap-1">
                                    <i class="bi bi-tag-fill text-[9px]"></i> Klik Tag di Bawah untuk Memasukkan ke Kursor Editor:
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" @click="insertTag('{nama}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{nama}</button>
                                    <button type="button" @click="insertTag('{nik}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{nik}</button>
                                    <button type="button" @click="insertTag('{ttl}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{ttl}</button>
                                    <button type="button" @click="insertTag('{tempat_lahir}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{tempat_lahir}</button>
                                    <button type="button" @click="insertTag('{tanggal_lahir}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{tanggal_lahir}</button>
                                    <button type="button" @click="insertTag('{alamat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-colors">{alamat}</button>
                                    <button type="button" @click="insertTag('{nisn}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30 hover:bg-sky-500/20 transition-colors">{nisn}</button>
                                    <button type="button" @click="insertTag('{nis}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30 hover:bg-sky-500/20 transition-colors">{nis}</button>
                                    <button type="button" @click="insertTag('{rombel}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30 hover:bg-sky-500/20 transition-colors">{rombel}</button>
                                    <button type="button" @click="insertTag('{niy}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/20 transition-colors">{niy}</button>
                                    <button type="button" @click="insertTag('{nuptk}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/20 transition-colors">{nuptk}</button>
                                    <button type="button" @click="insertTag('{jabatan}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/20 transition-colors">{jabatan}</button>
                                    <button type="button" @click="insertTag('{nomor_surat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30 hover:bg-violet-500/20 transition-colors">{nomor_surat}</button>
                                    <button type="button" @click="insertTag('{tanggal_surat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30 hover:bg-violet-500/20 transition-colors">{tanggal_surat}</button>
                                    <button type="button" @click="insertTag('{lembaga}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30 hover:bg-violet-500/20 transition-colors">{lembaga}</button>
                                    <button type="button" @click="insertTag('{kepala_lembaga}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-300 border border-amber-500/30 hover:bg-amber-500/20 transition-colors">{kepala_lembaga}</button>
                                    <button type="button" @click="insertTag('{nip_kepala}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-300 border border-amber-500/30 hover:bg-amber-500/20 transition-colors">{nip_kepala}</button>
                                </div>
                            </div>

                            {{-- WYSIWYG TOOLBAR --}}
                            <div class="rounded-2xl border border-slate-300 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800">
                                <div class="p-2 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex flex-wrap items-center gap-1 text-xs">
                                    <button type="button" @click="formatDoc('bold')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 font-bold" title="Tebal"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" @click="formatDoc('italic')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 italic" title="Miring"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" @click="formatDoc('underline')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 underline" title="Garis Bawah"><i class="bi bi-type-underline"></i></button>
                                    <button type="button" @click="formatDoc('strikeThrough')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 line-through" title="Coret"><i class="bi bi-type-strikethrough"></i></button>
                                    
                                    <span class="w-[1px] h-4 bg-slate-300 dark:bg-slate-600 mx-1"></span>

                                    <button type="button" @click="formatDoc('justifyLeft')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Rata Kiri"><i class="bi bi-text-left"></i></button>
                                    <button type="button" @click="formatDoc('justifyCenter')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Rata Tengah"><i class="bi bi-text-center"></i></button>
                                    <button type="button" @click="formatDoc('justifyRight')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Rata Kanan"><i class="bi bi-text-right"></i></button>
                                    <button type="button" @click="formatDoc('justifyFull')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Rata Kiri-Kanan"><i class="bi bi-justify"></i></button>

                                    <span class="w-[1px] h-4 bg-slate-300 dark:bg-slate-600 mx-1"></span>

                                    <button type="button" @click="formatDoc('insertUnorderedList')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Daftar Bullet"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" @click="formatDoc('insertOrderedList')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Daftar Angka"><i class="bi bi-list-ol"></i></button>
                                    <button type="button" @click="formatDoc('insertHorizontalRule')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Garis Pemisah"><i class="bi bi-hr"></i></button>

                                    <span class="w-[1px] h-4 bg-slate-300 dark:bg-slate-600 mx-1"></span>

                                    <button type="button" @click="formatDoc('undo')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <button type="button" @click="formatDoc('redo')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                                    <button type="button" @click="formatDoc('removeFormat')" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-rose-500" title="Hapus Format"><i class="bi bi-eraser"></i></button>
                                </div>

                                {{-- CONTENTEDITABLE VISUAL CANVAS --}}
                                <div id="wysiwyg-editor" 
                                     contenteditable="true" 
                                     @input="$wire.set('form_konten', $el.innerHTML)"
                                     class="p-5 min-h-[320px] max-h-[460px] overflow-y-auto text-sm leading-relaxed text-slate-800 dark:text-slate-100 font-serif focus:outline-none bg-white dark:bg-slate-900 border-none">
                                    {!! $form_konten !!}
                                </div>
                            </div>
                            @error('form_konten') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    {{-- SECTION 4: FILE UPLOAD (FOR UPLOAD MODE) --}}
                    @if ($form_jenis_pembuatan === 'upload')
                        <div class="p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-4">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Berkas Arsip Surat (PDF, Word, atau Scan Gambar) <span class="text-rose-500">*</span>
                            </label>

                            <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-6 text-center hover:border-cyan-500 transition-colors bg-white/50 dark:bg-slate-900/50">
                                <i class="bi bi-cloud-arrow-up-fill text-4xl text-cyan-500 mb-2 block"></i>
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                    Pilih file surat atau seret ke sini
                                </p>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    Mendukung format PDF, DOCX, JPG, PNG (Maksimal 10 MB)
                                </p>

                                <input type="file" 
                                       wire:model="form_file" 
                                       class="mt-3 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600 file:cursor-pointer cursor-pointer">
                            </div>

                            <div wire:loading wire:target="form_file" class="text-xs text-cyan-600 dark:text-cyan-400 font-bold flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-cyan-500 border-t-transparent rounded-full animate-spin"></div>
                                <span>Mengunggah berkas surat...</span>
                            </div>

                            @if ($existing_file_path && !$form_file)
                                <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2 truncate">
                                        <i class="bi bi-file-earmark-check-fill text-emerald-500 text-base"></i>
                                        <span class="font-bold text-slate-700 dark:text-slate-200">File Arsip Saat Ini:</span>
                                        <span class="text-slate-500 truncate">{{ basename($existing_file_path) }}</span>
                                    </div>
                                    <a href="{{ Storage::url($existing_file_path) }}" target="_blank" class="text-cyan-500 font-bold hover:underline">
                                        Lihat
                                    </a>
                                </div>
                            @endif
                            @error('form_file') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror

                            {{-- Catatan / Keterangan --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Keterangan / Catatan Arsip
                                </label>
                                <textarea wire:model="form_keterangan" 
                                          rows="2" 
                                          placeholder="Catatan fisik, rak penyimpanan, nomor map, dll..." 
                                          class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500"></textarea>
                            </div>
                        </div>
                    @endif

                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-900 flex items-center justify-between">
                    <button type="button" 
                            wire:click="$set('showModal', false)" 
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </button>

                    <div class="flex items-center gap-2">
                        @if ($form_jenis_pembuatan === 'template')
                            <button type="button" 
                                    wire:click="saveSurat(true)"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs flex items-center gap-2 transition-all">
                                <i class="bi bi-printer text-cyan-400"></i>
                                <span>Simpan & Cetak</span>
                            </button>
                        @endif

                        <button type="button" 
                                wire:click="saveSurat(false)"
                                wire:loading.attr="disabled"
                                class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-sky-500 to-cyan-500 hover:from-sky-600 hover:to-cyan-600 text-white font-bold text-xs shadow-md shadow-cyan-500/25 flex items-center gap-2 transition-all">
                            <i class="bi bi-check2-circle"></i>
                            <span>{{ $editingSuratId ? 'Perbarui Surat' : 'Simpan Surat' }}</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    {{-- ========================================================
         MODAL 2: KELOLA TEMPLATE WYSIWYG MASTER
    ======================================================== --}}
    @if ($showTemplateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5"
             x-transition>
            <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-6">
                
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-cyan-500/10 via-sky-500/5 to-transparent">
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-cyan-600 dark:text-cyan-400 font-display">
                            Master Template
                        </div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 font-display flex items-center gap-2">
                            <i class="bi bi-layout-text-window-reverse text-cyan-500"></i>
                            {{ $editingTemplateId ? 'Edit Desain Template Surat' : 'Desain Template Surat Baru' }}
                        </h2>
                    </div>

                    <button type="button" 
                            wire:click="$set('showTemplateModal', false)" 
                            class="p-2 rounded-2xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 max-h-[75vh] overflow-y-auto space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Nama Template <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" 
                                   wire:model="template_nama" 
                                   placeholder="Cth: Surat Keterangan Siswa Aktif" 
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500 font-semibold">
                            @error('template_nama') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Kode Surat <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="template_kode_surat" 
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-semibold focus:ring-2 focus:ring-cyan-500">
                                @foreach (daftar_kode_surat() as $kode => $label)
                                    <option value="{{ $kode }}">{{ $kode }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Lembaga Khusus (Opsional)
                            </label>
                            <select wire:model="template_lembaga_id" 
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                                @if (auth()->user()->isAdmin())
                                    <option value="">Berlaku untuk Semua Lembaga (Master)</option>
                                @endif
                                @foreach (daftar_lembaga() as $id => $nama)
                                    @if (auth()->user()->isAdmin() || in_array($id, $allowedLembaga))
                                        <option value="{{ $id }}">{{ $nama }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Deskripsi Singkat
                            </label>
                            <input type="text" 
                                   wire:model="template_deskripsi" 
                                   placeholder="Keterangan peruntukan template..." 
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-cyan-500">
                        </div>
                    </div>

                    {{-- TAG SHORTCUTS FOR TEMPLATE --}}
                    <div class="p-2.5 rounded-2xl bg-cyan-500/5 dark:bg-cyan-500/10 border border-cyan-500/20">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-cyan-700 dark:text-cyan-300 mb-1.5 flex items-center gap-1">
                            <i class="bi bi-tag-fill text-[9px]"></i> Klik Tag untuk Memasukkan ke Template:
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="insertTemplateTag('{nama}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{nama}</button>
                            <button type="button" @click="insertTemplateTag('{nik}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{nik}</button>
                            <button type="button" @click="insertTemplateTag('{ttl}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{ttl}</button>
                            <button type="button" @click="insertTemplateTag('{tempat_lahir}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{tempat_lahir}</button>
                            <button type="button" @click="insertTemplateTag('{tanggal_lahir}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{tanggal_lahir}</button>
                            <button type="button" @click="insertTemplateTag('{alamat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-cyan-500">{alamat}</button>
                            <button type="button" @click="insertTemplateTag('{nisn}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30">{nisn}</button>
                            <button type="button" @click="insertTemplateTag('{nis}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30">{nis}</button>
                            <button type="button" @click="insertTemplateTag('{rombel}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-300 border border-sky-500/30">{rombel}</button>
                            <button type="button" @click="insertTemplateTag('{niy}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30">{niy}</button>
                            <button type="button" @click="insertTemplateTag('{nuptk}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30">{nuptk}</button>
                            <button type="button" @click="insertTemplateTag('{jabatan}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/30">{jabatan}</button>
                            <button type="button" @click="insertTemplateTag('{nomor_surat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30">{nomor_surat}</button>
                            <button type="button" @click="insertTemplateTag('{tanggal_surat}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30">{tanggal_surat}</button>
                            <button type="button" @click="insertTemplateTag('{lembaga}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-300 border border-violet-500/30">{lembaga}</button>
                            <button type="button" @click="insertTemplateTag('{kepala_lembaga}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-300 border border-amber-500/30">{kepala_lembaga}</button>
                            <button type="button" @click="insertTemplateTag('{nip_kepala}')" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-300 border border-amber-500/30">{nip_kepala}</button>
                        </div>
                    </div>

                    {{-- QUICK IMPORT FROM WORD IN MODAL --}}
                    <div class="p-3 rounded-2xl bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-transparent border border-blue-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg">
                                <i class="bi bi-file-earmark-word-fill"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Impor Dokumen Word (.docx)</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">Pilih berkas Word untuk mengekstrak isi teks, tabel, dan format ke editor ini</div>
                            </div>
                        </div>

                        <div>
                            <label class="cursor-pointer px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-colors">
                                <i class="bi bi-upload"></i>
                                <span>Pilih Berkas Word</span>
                                <input type="file" wire:model="template_word_file" accept=".docx" class="hidden">
                            </label>
                        </div>
                    </div>

                    <div wire:loading wire:target="template_word_file" class="text-xs text-blue-600 dark:text-blue-400 font-bold flex items-center gap-2">
                        <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                        <span>Mengekstrak dan mengonversi berkas Word ke HTML template...</span>
                    </div>

                    {{-- WYSIWYG TOOLBAR FOR TEMPLATE --}}
                    <div class="rounded-2xl border border-slate-300 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800">
                        <div class="p-2 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex flex-wrap items-center gap-1 text-xs">
                            <button type="button" @click="formatTemplateDoc('bold')" class="p-1.5 rounded hover:bg-slate-200 font-bold"><i class="bi bi-type-bold"></i></button>
                            <button type="button" @click="formatTemplateDoc('italic')" class="p-1.5 rounded hover:bg-slate-200 italic"><i class="bi bi-type-italic"></i></button>
                            <button type="button" @click="formatTemplateDoc('underline')" class="p-1.5 rounded hover:bg-slate-200 underline"><i class="bi bi-type-underline"></i></button>
                            <span class="w-[1px] h-4 bg-slate-300 mx-1"></span>
                            <button type="button" @click="formatTemplateDoc('justifyLeft')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-text-left"></i></button>
                            <button type="button" @click="formatTemplateDoc('justifyCenter')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-text-center"></i></button>
                            <button type="button" @click="formatTemplateDoc('justifyRight')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-text-right"></i></button>
                            <button type="button" @click="formatTemplateDoc('justifyFull')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-justify"></i></button>
                            <span class="w-[1px] h-4 bg-slate-300 mx-1"></span>
                            <button type="button" @click="formatTemplateDoc('insertUnorderedList')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-list-ul"></i></button>
                            <button type="button" @click="formatTemplateDoc('insertOrderedList')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-list-ol"></i></button>
                            <button type="button" @click="formatTemplateDoc('insertHorizontalRule')" class="p-1.5 rounded hover:bg-slate-200"><i class="bi bi-hr"></i></button>
                        </div>

                        <div id="wysiwyg-template-editor" 
                             contenteditable="true" 
                             @input="$wire.set('template_konten', $el.innerHTML)"
                             class="p-5 min-h-[300px] max-h-[420px] overflow-y-auto text-sm leading-relaxed text-slate-800 dark:text-slate-100 font-serif focus:outline-none bg-white dark:bg-slate-900 border-none">
                            {!! $template_konten !!}
                        </div>
                    </div>
                    @error('template_konten') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-900 flex items-center justify-between">
                    <button type="button" 
                            wire:click="$set('showTemplateModal', false)" 
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </button>

                    <button type="button" 
                            wire:click="saveTemplate" 
                            class="px-5 py-2.5 rounded-2xl bg-cyan-500 hover:bg-cyan-600 text-white font-bold text-xs shadow-md shadow-cyan-500/25 flex items-center gap-2">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Template</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ========================================================
         MODAL 3: QUICK VIEW DETAIL SURAT
    ======================================================== --}}
    @if ($showDetailModal && $viewingSurat)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5"
             x-transition>
            <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-6">
                
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-sky-500/10 to-transparent">
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-cyan-600 dark:text-cyan-400 font-display">
                            Rincian Arsip Surat
                        </div>
                        <h2 class="text-base font-black text-slate-800 dark:text-slate-100 font-mono">
                            {{ $viewingSurat->nomor_surat }}
                        </h2>
                    </div>

                    <button type="button" 
                            wire:click="$set('showDetailModal', false)" 
                            class="p-2 rounded-2xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 max-h-[70vh] overflow-y-auto space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px]">Lembaga</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $viewingSurat->singkatan_lembaga }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Tanggal Surat</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $viewingSurat->tanggal_surat ? $viewingSurat->tanggal_surat->isoFormat('D MMMM Y') : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Klasifikasi</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $viewingSurat->kode_surat }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Metode</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 capitalize">{{ $viewingSurat->jenis_pembuatan }}</span>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Perihal</h4>
                        <div class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $viewingSurat->perihal }}</div>
                        @if ($viewingSurat->tujuan)
                            <div class="text-xs text-slate-500 mt-1">Yth: {{ $viewingSurat->tujuan }}</div>
                        @endif
                    </div>

                    @if ($viewingSurat->jenis_pembuatan === 'template')
                        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl p-5 bg-white dark:bg-slate-900 shadow-inner">
                            <div class="text-xs leading-relaxed text-slate-800 dark:text-slate-200 font-serif">
                                {!! $viewingSurat->konten !!}
                            </div>
                        </div>
                    @elseif ($viewingSurat->file_path)
                        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-file-earmark-arrow-down-fill text-2xl text-emerald-500"></i>
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ basename($viewingSurat->file_path) }}</div>
                                    <div class="text-[10px] text-slate-400">Berkas arsip surat eksternal / pindaian fisik</div>
                                </div>
                            </div>
                            <a href="{{ route('surat.download', $viewingSurat->id) }}" 
                               target="_blank"
                               class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs shadow-sm flex items-center gap-1.5">
                                <i class="bi bi-download"></i> Unduh File
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-900 flex items-center justify-between">
                    <button type="button" 
                            wire:click="$set('showDetailModal', false)" 
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-200">
                        Tutup
                    </button>

                    @if ($viewingSurat->jenis_pembuatan === 'template')
                        <a href="{{ route('surat.print', $viewingSurat->id) }}" 
                           target="_blank" 
                           class="px-4 py-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs flex items-center gap-2">
                            <i class="bi bi-printer"></i> Buka Tampilan Cetak
                        </a>
                    @endif
                </div>

            </div>
        </div>
    @endif

    {{-- ========================================================
         MODAL 4: IMPOR TEMPLATE DARI FILE WORD (.docx)
    ======================================================== --}}
    @if ($showImportWordModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5"
             x-transition>
            <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-6">
                
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-blue-600/10 via-indigo-600/5 to-transparent">
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400 font-display">
                            Konversi Dokumen Eksternal
                        </div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 font-display flex items-center gap-2">
                            <i class="bi bi-file-earmark-word-fill text-blue-600 dark:text-blue-400"></i>
                            Impor Template dari Berkas Word (.docx)
                        </h2>
                    </div>

                    <button type="button" 
                            wire:click="$set('showImportWordModal', false)" 
                            class="p-2 rounded-2xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 max-h-[75vh] overflow-y-auto space-y-4">
                    {{-- Dropzone --}}
                    <div class="border-2 border-dashed border-blue-300 dark:border-blue-700/60 rounded-2xl p-6 text-center hover:border-blue-500 bg-blue-50/30 dark:bg-slate-800/40 transition-colors">
                        <i class="bi bi-cloud-arrow-up-fill text-4xl text-blue-600 dark:text-blue-400 mb-2 block"></i>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">
                            Unggah Dokumen Word (.docx)
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Sistem akan mengekstrak paragraf, perataan teks, tabel, garis bawah, serta tag dinamis seperti <code class="text-blue-600 font-mono">{nama}</code>, <code class="text-blue-600 font-mono">{nik}</code>, <code class="text-blue-600 font-mono">{ttl}</code>
                        </p>

                        <input type="file" 
                               wire:model="import_file" 
                               accept=".docx"
                               class="mt-3 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer cursor-pointer">
                    </div>

                    <div wire:loading wire:target="import_file" class="text-xs text-blue-600 dark:text-blue-400 font-bold flex items-center gap-2">
                        <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                        <span>Sedang membaca dan mengekstrak dokumen Word...</span>
                    </div>

                    @error('import_file') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror

                    {{-- Form Fields --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Nama Template Baru <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" 
                                   wire:model="import_nama" 
                                   placeholder="Otomatis dari nama file atau sesuaikan..." 
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-semibold focus:ring-2 focus:ring-blue-500">
                            @error('import_nama') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Kode Surat <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="import_kode_surat" 
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-semibold focus:ring-2 focus:ring-blue-500">
                                @foreach (daftar_kode_surat() as $kode => $label)
                                    <option value="{{ $kode }}">{{ $kode }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Berlaku Khusus Lembaga (Opsional)
                            </label>
                            <select wire:model="import_lembaga_id" 
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500">
                                @if (auth()->user()->isAdmin())
                                    <option value="">Semua Lembaga (Master)</option>
                                @endif
                                @foreach (daftar_lembaga() as $id => $nama)
                                    @if (auth()->user()->isAdmin() || in_array($id, $allowedLembaga))
                                        <option value="{{ $id }}">{{ $nama }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Deskripsi Template
                            </label>
                            <input type="text" 
                                   wire:model="import_deskripsi" 
                                   placeholder="Contoh: Hasil impor dokumen resmi dari arsip TU" 
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    {{-- Live Preview Box --}}
                    @if ($import_preview_html)
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <i class="bi bi-eye text-blue-500"></i>
                                    <span>Pratinjau Hasil Ekstraksi Word</span>
                                </label>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-full">
                                    Siap Disimpan
                                </span>
                            </div>

                            <div class="border border-slate-300 dark:border-slate-700 rounded-2xl p-5 bg-white dark:bg-slate-900 max-h-72 overflow-y-auto text-xs leading-relaxed text-slate-800 dark:text-slate-200 font-serif shadow-inner">
                                {!! $import_preview_html !!}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-900 flex items-center justify-between">
                    <button type="button" 
                            wire:click="$set('showImportWordModal', false)" 
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-200">
                        Batal
                    </button>

                    <div class="flex items-center gap-2">
                        @if ($import_preview_html)
                            <button type="button" 
                                    wire:click="saveImportedWordTemplate(true)" 
                                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs flex items-center gap-1.5 transition-all">
                                <i class="bi bi-pencil-square text-cyan-400"></i>
                                <span>Buka di Editor Visual</span>
                            </button>
                        @endif

                        <button type="button" 
                                wire:click="saveImportedWordTemplate(false)" 
                                {{ empty($import_preview_html) ? 'disabled' : '' }}
                                class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 text-white font-bold text-xs shadow-md shadow-blue-500/25 flex items-center gap-2 transition-all">
                            <i class="bi bi-check2-circle"></i>
                            <span>Simpan Template Langsung</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>
