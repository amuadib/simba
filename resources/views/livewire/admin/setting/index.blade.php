@section('title', 'Pengaturan Sistem')

<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public string $app_nama = '';
    public string $app_singkatan = '';
    public string $app_keterangan = '';

    public array $lembaga_items = [];
    public array $logos = [];
    public array $kops = [];

    public string $activeTab = 'app'; // 'app', 'lembaga', 'api'

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // 1. Ambil Pengaturan Aplikasi
        $appConfig = Setting::getValue('app') ?? config('local.app') ?? config('custom.app') ?? [];
        $this->app_nama = $appConfig['nama'] ?? Setting::getValue('nama_aplikasi', 'Sistem Informasi Lembaga');
        $this->app_singkatan = $appConfig['singkatan'] ?? 'SIMBA';
        $this->app_keterangan = $appConfig['keterangan'] ?? '';

        // 2. Ambil Pengaturan Lembaga
        $lembagaItems = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];

        // Pastikan format array jika dari config/json
        if (is_object($lembagaItems)) {
            $lembagaItems = json_decode(json_encode($lembagaItems), true);
        }

        foreach ($lembagaItems as $id => &$lembaga) {
            $tingkat = $lembaga['tingkat'] ?? [];
            $lembaga['tingkat_string'] = is_array($tingkat) ? implode(', ', $tingkat) : (string) $tingkat;

            $jamKerja = $lembaga['jam_kerja'] ?? [];
            $lembaga['jam_kerja_string'] = is_array($jamKerja) ? implode("\n", $jamKerja) : (string) $jamKerja;
        }

        $this->lembaga_items = $lembagaItems;
    }

    public function addLembaga(): void
    {
        $newId = empty($this->lembaga_items) ? 1 : max(array_keys($this->lembaga_items)) + 1;
        $this->lembaga_items[$newId] = [
            'nama' => 'Nama Lembaga Baru',
            'singkatan' => '',
            'alamat' => '',
            'kontak' => '',
            'telp' => '',
            'lat' => 0,
            'lon' => 0,
            'logo' => null,
            'kop' => null,
            'tingkat' => [],
            'jam_kerja' => [],
            'tingkat_string' => '',
            'jam_kerja_string' => '',
        ];
        $this->activeTab = 'lembaga';
    }

    public function removeLembaga($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            unset($this->lembaga_items[$id]);
        }
        unset($this->logos[$id], $this->kops[$id]);
        $this->dispatch('toast', message: "Lembaga #$id berhasil dihapus dari daftar", type: 'warning');
    }

    public function removeLogo($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            $this->lembaga_items[$id]['logo'] = null;
        }
        unset($this->logos[$id]);
    }

    public function removeKop($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            $this->lembaga_items[$id]['kop'] = null;
        }
        unset($this->kops[$id]);
    }

    public function save(): void
    {
        $this->validate([
            'app_nama' => 'required|string|max:255',
            'app_singkatan' => 'required|string|max:50',
            'app_keterangan' => 'nullable|string',
            'lembaga_items' => 'array',
            'logos.*' => 'nullable|image|max:2048',
            'kops.*' => 'nullable|image|max:3072',
        ]);

        $appConfig = [
            'nama' => $this->app_nama,
            'singkatan' => $this->app_singkatan,
            'keterangan' => $this->app_keterangan,
        ];

        // Simpan konfigurasi aplikasi
        Setting::setValue('app', $appConfig);

        // Upload Logo lembaga
        foreach ($this->logos as $id => $logoFile) {
            if ($logoFile && isset($this->lembaga_items[$id])) {
                $path = $logoFile->store('lembaga/logo', 'public');
                $this->lembaga_items[$id]['logo'] = Storage::url($path);
            }
        }

        // Upload Kop Surat lembaga
        foreach ($this->kops as $id => $kopFile) {
            if ($kopFile && isset($this->lembaga_items[$id])) {
                $path = $kopFile->store('lembaga/kop', 'public');
                $this->lembaga_items[$id]['kop'] = Storage::url($path);
            }
        }

        // Format data lembaga sebelum disimpan
        $payloadToSave = $this->lembaga_items;
        foreach ($payloadToSave as &$lembaga) {
            if (isset($lembaga['tingkat_string'])) {
                $tingkat = array_map('trim', explode(',', $lembaga['tingkat_string']));
                $lembaga['tingkat'] = array_values(array_filter($tingkat, fn ($val) => $val !== ''));
                unset($lembaga['tingkat_string']);
            }
            if (isset($lembaga['jam_kerja_string'])) {
                $jam_kerja = array_map('trim', explode("\n", $lembaga['jam_kerja_string']));
                $lembaga['jam_kerja'] = array_values(array_filter($jam_kerja, fn ($val) => $val !== ''));
                unset($lembaga['jam_kerja_string']);
            }
        }

        // Simpan konfigurasi lembaga
        Setting::setValue('lembaga', $payloadToSave);

        // Backward compatibility dengan kolom lama di SIMBA
        Setting::setValue('nama_aplikasi', $this->app_nama);
        if (!empty($this->lembaga_items)) {
            $firstLembaga = reset($this->lembaga_items);
            if (!empty($firstLembaga['nama'])) {
                Setting::setValue('nama_lembaga', $firstLembaga['nama']);
            }
            if (!empty($firstLembaga['alamat'])) {
                Setting::setValue('alamat', $firstLembaga['alamat']);
            }
            if (!empty($firstLembaga['telp'])) {
                Setting::setValue('telp', $firstLembaga['telp']);
            }
            if (!empty($firstLembaga['logo'])) {
                Setting::setValue('logo', $firstLembaga['logo']);
            }
        }

        $this->logos = [];
        $this->kops = [];

        $this->dispatch('toast', message: 'Pengaturan Sistem berhasil disimpan!', type: 'success');
    }
};
?>

<div>
    {{-- Header Halaman --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold">Pengaturan Sistem (Master)</h4>
            <small class="text-muted">Kelola data master aplikasi dan multi-lembaga yayasan</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- Navigasi Tab & Tombol Simpan --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <ul class="nav nav-pills gap-2 flex-wrap" role="tablist">
            <li class="nav-item">
                <button type="button"
                        class="nav-link {{ $activeTab === 'app' ? 'active' : '' }} d-flex align-items-center gap-2"
                        wire:click="$set('activeTab', 'app')">
                    <i class="bi bi-gear-fill"></i>
                    <span>Informasi Aplikasi</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button"
                        class="nav-link {{ $activeTab === 'lembaga' ? 'active' : '' }} d-flex align-items-center gap-2"
                        wire:click="$set('activeTab', 'lembaga')">
                    <i class="bi bi-buildings"></i>
                    <span>Daftar Lembaga</span>
                    <span class="badge bg-secondary rounded-pill ms-1">{{ count($lembaga_items) }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button"
                        class="nav-link {{ $activeTab === 'api' ? 'active' : '' }} d-flex align-items-center gap-2"
                        wire:click="$set('activeTab', 'api')">
                    <i class="bi bi-link-45deg"></i>
                    <span>Integrasi Client (SAKU-v2)</span>
                </button>
            </li>
        </ul>

        <button type="button" wire:click="save" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <i wire:loading.remove wire:target="save" class="bi bi-save2"></i>
            <span>Simpan Pengaturan</span>
        </button>
    </div>

    {{-- KONTEN TAB 1: INFORMASI APLIKASI --}}
    @if ($activeTab === 'app')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-sliders me-2"></i>Informasi Master Aplikasi
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nama Aplikasi <span class="text-danger">*</span></label>
                        <input type="text"
                               wire:model="app_nama"
                               class="form-control @error('app_nama') is-invalid @enderror"
                               placeholder="Contoh: Sistem Informasi Lembaga">
                        @error('app_nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Nama utama aplikasi yang tampil di dashboard dan judul tab.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Singkatan Aplikasi <span class="text-danger">*</span></label>
                        <input type="text"
                               wire:model="app_singkatan"
                               class="form-control @error('app_singkatan') is-invalid @enderror"
                               placeholder="Contoh: SIMBA">
                        @error('app_singkatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Singkatan atau akronim aplikasi.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Keterangan / Deskripsi Aplikasi</label>
                        <textarea wire:model="app_keterangan"
                                  rows="3"
                                  class="form-control @error('app_keterangan') is-invalid @enderror"
                                  placeholder="Deskripsi singkat mengenai sistem ini..."></textarea>
                        @error('app_keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- KONTEN TAB 2: DAFTAR LEMBAGA --}}
    @if ($activeTab === 'lembaga')
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0 fw-bold">Multi-Lembaga Yayasan</h5>
                <small class="text-muted">Kelola seluruh lembaga sekolah/madrasah dan yayasan di bawah naungan sistem</small>
            </div>
            <button type="button" wire:click="addLembaga" class="btn btn-success btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-circle"></i>
                <span>Tambah Lembaga</span>
            </button>
        </div>

        <div class="space-y-4">
            @forelse ($lembaga_items as $id => $lembaga)
                <div class="card shadow-sm border-0 mb-4" wire:key="lembaga-card-{{ $id }}">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary px-2.5 py-1.5 fs-6">#{{ $id }}</span>
                            <span class="fw-bold fs-6 text-truncate" style="max-width: 400px;">{{ $lembaga['nama'] ?? 'Lembaga Tanpa Nama' }}</span>
                            @if (!empty($lembaga['singkatan']))
                                <span class="badge bg-light text-dark border">({{ $lembaga['singkatan'] }})</span>
                            @endif
                        </div>
                        <button type="button"
                                wire:click="removeLembaga({{ $id }})"
                                wire:confirm="Yakin ingin menghapus lembaga #{{ $id }} ({{ $lembaga['nama'] ?? '' }})?"
                                class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1">
                            <i class="bi bi-trash"></i>
                            <span>Hapus Lembaga</span>
                        </button>
                    </div>

                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label class="form-label fw-semibold">Nama Lembaga <span class="text-danger">*</span></label>
                                <input type="text"
                                       wire:model="lembaga_items.{{ $id }}.nama"
                                       class="form-control"
                                       placeholder="Contoh: SDI Miftahul Ulum Klemunan">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Singkatan / Alias</label>
                                <input type="text"
                                       wire:model="lembaga_items.{{ $id }}.singkatan"
                                       class="form-control"
                                       placeholder="Contoh: SDI / SMPI / SMAI">
                            </div>

                            <div class="col-12 col-md-6 col-xl-4">
                                <label class="form-label fw-semibold">Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.telp"
                                           class="form-control"
                                           placeholder="08123456789">
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-xl-4">
                                <label class="form-label fw-semibold">Kontak Person (PIC)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.kontak"
                                           class="form-control"
                                           placeholder="Nama kepala / bendahara">
                                </div>
                            </div>

                            <div class="col-12 col-md-12 col-xl-4">
                                <label class="form-label fw-semibold">Tingkat Kelas</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-layers"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.tingkat_string"
                                           class="form-control"
                                           placeholder="Contoh: 1, 2, 3, 4, 5, 6">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Jam Kerja (1 baris per jadwal)</label>
                                <textarea wire:model="lembaga_items.{{ $id }}.jam_kerja_string"
                                          rows="3"
                                          class="form-control"
                                          placeholder="Senin - Sabtu: 07:00 - 13:30&#10;Jum'at: 07:00 - 10:00&#10;Ahad Libur"></textarea>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Alamat Lengkap</label>
                                <textarea wire:model="lembaga_items.{{ $id }}.alamat"
                                          rows="3"
                                          class="form-control"
                                          placeholder="Jl. Manggar RT 02 RW 06 Klemunan..."></textarea>
                            </div>

                            {{-- BAGIAN LOGO & KOP SURAT --}}
                            <div class="col-12 pt-3 border-top mt-3">
                                <div class="row g-4">
                                    {{-- Logo Lembaga --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-image me-1"></i>Logo Lembaga</span>
                                            <span wire:loading wire:target="logos.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded border p-1 bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width: 70px; height: 70px; overflow: hidden;">
                                                @if (isset($logos[$id]) && method_exists($logos[$id], 'temporaryUrl'))
                                                    <img src="{{ $logos[$id]->temporaryUrl() }}" alt="Preview Logo" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @elseif (!empty($lembaga['logo']))
                                                    <img src="{{ asset($lembaga['logo']) }}" alt="Logo" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @else
                                                    <i class="bi bi-image text-muted fs-3"></i>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                                                <input type="file"
                                                       wire:model="logos.{{ $id }}"
                                                       accept="image/*"
                                                       class="form-control form-control-sm">
                                                @error('logos.'.$id)
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                                @if (!empty($lembaga['logo']))
                                                    <button type="button" wire:click="removeLogo({{ $id }})" class="btn btn-link btn-sm text-danger p-0 mt-1">
                                                        <i class="bi bi-x-circle me-1"></i>Hapus Logo
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Kop Surat Lembaga --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-file-earmark-richtext me-1"></i>Kop Surat</span>
                                            <span wire:loading wire:target="kops.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded border p-1 bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width: 120px; height: 70px; overflow: hidden;">
                                                @if (isset($kops[$id]) && method_exists($kops[$id], 'temporaryUrl'))
                                                    <img src="{{ $kops[$id]->temporaryUrl() }}" alt="Preview Kop" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @elseif (!empty($lembaga['kop']))
                                                    <img src="{{ asset($lembaga['kop']) }}" alt="Kop Surat" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @else
                                                    <i class="bi bi-file-earmark-richtext text-muted fs-3"></i>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                                                <input type="file"
                                                       wire:model="kops.{{ $id }}"
                                                       accept="image/*"
                                                       class="form-control form-control-sm">
                                                @error('kops.'.$id)
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                                @if (!empty($lembaga['kop']))
                                                    <button type="button" wire:click="removeKop({{ $id }})" class="btn btn-link btn-sm text-danger p-0 mt-1">
                                                        <i class="bi bi-x-circle me-1"></i>Hapus Kop Surat
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card shadow-sm border-0 p-5 text-center">
                    <i class="bi bi-building-exclamation text-muted display-4 mb-3"></i>
                    <h5 class="text-muted">Belum ada data lembaga terdaftar.</h5>
                    <p class="text-muted small">Klik tombol di bawah untuk menambahkan lembaga pertama.</p>
                    <div>
                        <button type="button" wire:click="addLembaga" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>Tambah Lembaga
                        </button>
                    </div>
                </div>
            @endforelse
        </div>
    @endif

    {{-- KONTEN TAB 3: INTEGRASI CLIENT (SAKU-v2) --}}
    @if ($activeTab === 'api')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-hdd-network me-2"></i>Konfigurasi Simba Master & API Client (SAKU-v2)
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info d-flex align-items-start gap-3">
                    <i class="bi bi-info-circle-fill fs-4 mt-1"></i>
                    <div>
                        <h6 class="fw-bold mb-1">SIMBA sebagai Sistem Master</h6>
                        <p class="mb-0 small">
                            Aplikasi <strong>SAKU-v2</strong> (dan aplikasi yayasan lainnya) akan mengambil master data lembaga (nama, tingkat, kontak, logo, kop) dari SIMBA melalui API berikut.
                        </p>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint Sync Lembaga</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary fw-bold">GET</span>
                            <input type="text"
                                   readonly
                                   class="form-control bg-light font-monospace"
                                   value="{{ url('/api/lembaga/sync') }}">
                        </div>
                        <small class="text-muted">Gunakan header <code>Authorization: Bearer &lt;Token&gt;</code> saat memanggil API ini.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint Sync Siswa & Rombel</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary fw-bold">GET</span>
                            <input type="text"
                                   readonly
                                   class="form-control bg-light font-monospace"
                                   value="{{ url('/api/siswa/sync') }}">
                        </div>
                        <small class="text-muted">API sinkronisasi siswa aktif ke SAKU-v2.</small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Secret Key / Bearer Token SAKU</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill text-warning"></i></span>
                            <input type="text"
                                   readonly
                                   class="form-control bg-light font-monospace"
                                   value="{{ config('local.saku.key', env('SAKU_KEY', 'secret-token-123')) }}">
                        </div>
                        <small class="text-muted">Dikonfigurasi di file <code>.env</code> dengan variabel <code>SAKU_KEY</code> atau <code>config/local.php</code>.</small>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
