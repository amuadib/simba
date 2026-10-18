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
    public array $ttds = [];
    public array $stempels = [];

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

            $lembaga['email'] = $lembaga['email'] ?? '';
            $lembaga['npsn'] = $lembaga['npsn'] ?? '';
            $lembaga['akreditasi'] = $lembaga['akreditasi'] ?? '';
            $lembaga['website'] = $lembaga['website'] ?? '';
            $lembaga['lat'] = $lembaga['lat'] ?? '';
            $lembaga['lon'] = $lembaga['lon'] ?? '';
            $lembaga['maps_embed'] = $lembaga['maps_embed'] ?? '';
        }

        $this->lembaga_items = $lembagaItems;
    }

    public function addLembaga(): void
    {
        $existingKeys = array_filter(array_keys($this->lembaga_items), fn($k) => is_numeric($k) && (int)$k !== 99);
        $newId = empty($existingKeys) ? 1 : max(array_map('intval', $existingKeys)) + 1;
        while ($newId === 99 || isset($this->lembaga_items[$newId])) {
            $newId++;
        }

        $this->lembaga_items[$newId] = [
            'nama' => 'Nama Lembaga Baru',
            'singkatan' => '',
            'npsn' => '',
            'akreditasi' => '',
            'website' => '',
            'alamat' => '',
            'kontak' => '',
            'kepala' => '',
            'nip_kepala' => '',
            'telp' => '',
            'email' => '',
            'lat' => '',
            'lon' => '',
            'maps_embed' => '',
            'logo' => null,
            'kop' => null,
            'ttd' => null,
            'stempel' => null,
            'tingkat' => [],
            'jam_kerja' => [],
            'tingkat_string' => '',
            'jam_kerja_string' => '',
        ];
        $this->activeTab = 'lembaga';
        $this->dispatch('lembaga-added', id: $newId);
    }

    public function removeLembaga($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            unset($this->lembaga_items[$id]);
        }
        unset($this->logos[$id], $this->kops[$id], $this->ttds[$id], $this->stempels[$id]);
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

    public function removeTtd($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            $this->lembaga_items[$id]['ttd'] = null;
        }
        unset($this->ttds[$id]);
    }

    public function removeStempel($id): void
    {
        if (isset($this->lembaga_items[$id])) {
            $this->lembaga_items[$id]['stempel'] = null;
        }
        unset($this->stempels[$id]);
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
            'ttds.*' => 'nullable|image|max:2048',
            'stempels.*' => 'nullable|image|max:2048',
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

        // Upload TTD Kepala lembaga
        foreach ($this->ttds as $id => $ttdFile) {
            if ($ttdFile && isset($this->lembaga_items[$id])) {
                $path = $ttdFile->store('lembaga/ttd', 'public');
                $this->lembaga_items[$id]['ttd'] = Storage::url($path);
            }
        }

        // Upload Stempel lembaga
        foreach ($this->stempels as $id => $stempelFile) {
            if ($stempelFile && isset($this->lembaga_items[$id])) {
                $path = $stempelFile->store('lembaga/stempel', 'public');
                $this->lembaga_items[$id]['stempel'] = Storage::url($path);
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
            if (!empty($firstLembaga['email'])) {
                Setting::setValue('email', $firstLembaga['email']);
            }
            if (!empty($firstLembaga['logo'])) {
                Setting::setValue('logo', $firstLembaga['logo']);
            }
            if (!empty($firstLembaga['ttd'])) {
                Setting::setValue('ttd_kepala', $firstLembaga['ttd']);
            }
            if (!empty($firstLembaga['stempel'])) {
                Setting::setValue('stempel_lembaga', $firstLembaga['stempel']);
            }
            if (!empty($firstLembaga['kepala'])) {
                Setting::setValue('kepala_lembaga', $firstLembaga['kepala']);
            }
            if (!empty($firstLembaga['lat'])) {
                Setting::setValue('lat', $firstLembaga['lat']);
            }
            if (!empty($firstLembaga['lon'])) {
                Setting::setValue('lon', $firstLembaga['lon']);
            }
            if (!empty($firstLembaga['maps_embed'])) {
                Setting::setValue('maps_embed', $firstLembaga['maps_embed']);
            }
            if (!empty($firstLembaga['npsn'])) {
                Setting::setValue('npsn', $firstLembaga['npsn']);
            }
            if (!empty($firstLembaga['akreditasi'])) {
                Setting::setValue('akreditasi', $firstLembaga['akreditasi']);
            }
            if (!empty($firstLembaga['website'])) {
                Setting::setValue('website', $firstLembaga['website']);
            }
        }

        $this->logos = [];
        $this->kops = [];
        $this->ttds = [];
        $this->stempels = [];

        $this->dispatch('toast', message: 'Pengaturan Sistem berhasil disimpan!', type: 'success');
    }

    public function getMapsEmbedUrl($id): ?string
    {
        $lembaga = $this->lembaga_items[$id] ?? null;
        if (!$lembaga) {
            return null;
        }

        $embed = trim($lembaga['maps_embed'] ?? '');
        if (!empty($embed)) {
            if (preg_match('/src=["\']([^"\']+)["\']/', $embed, $matches)) {
                return $matches[1];
            }
            if (str_starts_with($embed, 'http')) {
                return $embed;
            }
        }

        $lat = trim((string) ($lembaga['lat'] ?? ''));
        $lon = trim((string) ($lembaga['lon'] ?? ''));
        if (!empty($lat) && !empty($lon) && (float) $lat != 0 && (float) $lon != 0) {
            return "https://maps.google.com/maps?q={$lat},{$lon}&hl=id&z=16&output=embed";
        }

        if (!empty($lembaga['alamat'])) {
            return "https://maps.google.com/maps?q=" . urlencode($lembaga['alamat']) . "&hl=id&z=15&output=embed";
        }

        return null;
    }
};
?>

<div>
    {{-- Header Halaman --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold text-gradient-logo font-display">Pengaturan Sistem (Master)</h4>
            <small class="text-muted">Kelola data master aplikasi dan multi-lembaga yayasan</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- Navigasi Tab & Tombol Simpan --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="inline-flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/90 dark:border-slate-700/80 shadow-xs flex-wrap">
            <button type="button"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 font-display {{ $activeTab === 'app' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60' }}"
                    wire:click="$set('activeTab', 'app')">
                <i class="bi bi-gear-fill text-sky-500 dark:text-cyan-400"></i>
                <span>Informasi Aplikasi</span>
            </button>
            <button type="button"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 font-display {{ $activeTab === 'lembaga' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60' }}"
                    wire:click="$set('activeTab', 'lembaga')">
                <i class="bi bi-buildings text-sky-500 dark:text-cyan-400"></i>
                <span>Daftar Lembaga</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full {{ $activeTab === 'lembaga' ? 'bg-sky-100 text-sky-700 dark:bg-cyan-500/20 dark:text-cyan-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">{{ count($lembaga_items) }}</span>
            </button>
            <button type="button"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 font-display {{ $activeTab === 'api' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-cyan-400 shadow-xs border border-slate-200/80 dark:border-cyan-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-slate-800/60' }}"
                    wire:click="$set('activeTab', 'api')">
                <i class="bi bi-link-45deg text-sky-500 dark:text-cyan-400"></i>
                <span>Integrasi Client (SAKU-v2)</span>
            </button>
        </div>

        <button type="button" wire:click="save" class="btn-logo-gradient inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white shadow-md hover:shadow-lg transition-all">
            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <i wire:loading.remove wire:target="save" class="bi bi-save2"></i>
            <span>Simpan Pengaturan</span>
        </button>
    </div>

    {{-- KONTEN TAB 1: INFORMASI APLIKASI --}}
    @if ($activeTab === 'app')
        <div class="card shadow-sm border border-slate-200/90 dark:border-cyan-500/20 mb-4 rounded-2xl overflow-hidden">
            <div class="card-header bg-slate-50/90 dark:bg-slate-800/90 border-b border-slate-200/90 dark:border-cyan-500/20 py-3.5 px-4">
                <h6 class="mb-0 fw-bold text-sky-600 dark:text-cyan-400 d-flex align-items-center gap-2">
                    <i class="bi bi-sliders"></i> Informasi Master Aplikasi
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
        <div x-data="{
            openLembaga: [{{ !empty($lembaga_items) ? (int) array_key_first($lembaga_items) : 1 }}],
            toggle(id) {
                const numId = Number(id);
                if (this.openLembaga.includes(numId)) {
                    this.openLembaga = this.openLembaga.filter(i => i !== numId);
                } else {
                    this.openLembaga.push(numId);
                }
            },
            isOpen(id) {
                return this.openLembaga.includes(Number(id));
            },
            expandAll(allIds) {
                this.openLembaga = allIds.map(Number);
            },
            collapseAll() {
                this.openLembaga = [];
            }
        }"
        x-on:lembaga-added.window="const newId = Number($event.detail.id); if (!openLembaga.includes(newId)) { openLembaga.push(newId); }"
        class="w-100">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h5 class="mb-0 fw-bold text-slate-800 dark:text-slate-100 font-display">Multi-Lembaga Yayasan</h5>
                    <small class="text-muted">Kelola seluruh lembaga sekolah/madrasah dan yayasan di bawah naungan sistem</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if (count($lembaga_items) > 1)
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm rounded-xl px-3 py-2 text-xs font-semibold d-flex align-items-center gap-1.5 shadow-2xs"
                                @click="openLembaga.length === {{ count($lembaga_items) }} ? collapseAll() : expandAll(@js(array_map('intval', array_keys($lembaga_items))))">
                            <i class="bi" :class="openLembaga.length === {{ count($lembaga_items) }} ? 'bi-chevron-contract' : 'bi-chevron-expand'"></i>
                            <span x-text="openLembaga.length === {{ count($lembaga_items) }} ? 'Tutup Semua' : 'Buka Semua'">Buka Semua</span>
                        </button>
                    @endif
                    <button type="button" wire:click="addLembaga" class="btn-logo-gradient inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white shadow-sm hover:shadow-md transition-all">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Lembaga</span>
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                @forelse ($lembaga_items as $id => $lembaga)
                    <div class="card shadow-sm border border-slate-200/90 dark:border-cyan-500/20 mb-3 rounded-2xl overflow-hidden transition-all duration-200"
                         :class="isOpen({{ $id }}) ? 'ring-1 ring-sky-500/30 dark:ring-cyan-500/30' : ''"
                         wire:key="lembaga-card-{{ $id }}">
                        
                        {{-- Header Accordion --}}
                        <div class="card-header bg-slate-50/90 dark:bg-slate-800/90 border-b border-slate-200/90 dark:border-cyan-500/20 py-3.5 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2 select-none"
                             role="button"
                             tabindex="0"
                             @click="toggle({{ $id }})"
                             @keydown.enter.prevent="toggle({{ $id }})"
                             @keydown.space.prevent="toggle({{ $id }})"
                             style="cursor: pointer;">
                            
                            <div class="d-flex align-items-center gap-2.5 flex-wrap">
                                <span class="badge bg-primary px-2.5 py-1.5 rounded-lg text-xs font-bold shadow-2xs">#{{ $id }}</span>
                                <span class="fw-bold fs-6 text-slate-900 dark:text-slate-100 text-truncate" style="max-width: 360px;">
                                    {{ $lembaga['nama'] ?? 'Lembaga Tanpa Nama' }}
                                </span>
                                @if (!empty($lembaga['singkatan']))
                                    <span class="badge bg-sky-100 text-sky-700 dark:bg-cyan-500/20 dark:text-cyan-300 border border-sky-200 dark:border-cyan-500/30 rounded-md">
                                        ({{ $lembaga['singkatan'] }})
                                    </span>
                                @endif

                                {{-- Ringkasan singkat saat tertutup --}}
                                @if (!empty($lembaga['npsn']))
                                    <span class="badge bg-slate-200/80 text-slate-600 dark:bg-slate-700/60 dark:text-slate-300 text-[11px] font-normal border border-slate-300/60 dark:border-slate-600/50 d-none d-sm-inline-flex align-items-center gap-1">
                                        <i class="bi bi-card-heading text-sky-500"></i> NPSN: {{ $lembaga['npsn'] }}
                                    </span>
                                @endif
                                @if (!empty($lembaga['kepala']))
                                    <span class="badge bg-slate-200/80 text-slate-600 dark:bg-slate-700/60 dark:text-slate-300 text-[11px] font-normal border border-slate-300/60 dark:border-slate-600/50 d-none d-md-inline-flex align-items-center gap-1 text-truncate" style="max-width: 220px;">
                                        <i class="bi bi-person text-sky-500"></i> {{ $lembaga['kepala'] }}
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button type="button"
                                        @click.stop
                                        wire:click="removeLembaga({{ $id }})"
                                        wire:confirm="Yakin ingin menghapus lembaga #{{ $id }} ({{ $lembaga['nama'] ?? '' }})?"
                                        class="btn btn-outline-danger btn-sm rounded-xl px-2.5 py-1.5 text-xs font-semibold d-flex align-items-center gap-1"
                                        title="Hapus Lembaga">
                                    <i class="bi bi-trash"></i>
                                    <span class="d-none d-sm-inline">Hapus</span>
                                </button>

                                <div class="d-flex align-items-center justify-content-center rounded-xl bg-slate-200/80 dark:bg-slate-700/70 text-slate-700 dark:text-slate-200 transition-transform duration-200 shadow-2xs"
                                     style="width: 32px; height: 32px;"
                                     :style="isOpen({{ $id }}) ? 'transform: rotate(180deg);' : ''"
                                     aria-label="Toggle detail">
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        {{-- Konten Accordion Body --}}
                        <div class="card-body p-4 border-top border-slate-200/70 dark:border-slate-800/80"
                             x-show="isOpen({{ $id }})"
                             x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0">
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

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">NPSN</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.npsn"
                                           class="form-control"
                                           placeholder="Contoh: 20512345">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Status Akreditasi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-patch-check"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.akreditasi"
                                           class="form-control"
                                           placeholder="Contoh: A / Unggul / B">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Website Lembaga</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.website"
                                           class="form-control"
                                           placeholder="https://sdi-mu.sch.id">
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold">Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.telp"
                                           class="form-control"
                                           placeholder="08123456789">
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold">Email Lembaga</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email"
                                           wire:model="lembaga_items.{{ $id }}.email"
                                           class="form-control"
                                           placeholder="info@sekolah.sch.id">
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold">Kontak Person (PIC)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.kontak"
                                           class="form-control"
                                           placeholder="Nama staf / bendahara">
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
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
                                <label class="form-label fw-semibold">Nama Kepala Lembaga / Madrasah</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.kepala"
                                           class="form-control"
                                           placeholder="Contoh: H. Ahmad Dahlan, S.Pd.I">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">NIP / NIY Kepala Lembaga</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                    <input type="text"
                                           wire:model="lembaga_items.{{ $id }}.nip_kepala"
                                           class="form-control"
                                           placeholder="Contoh: 19800101 200501 1 001">
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

                            {{-- BAGIAN LOKASI & GOOGLE MAPS --}}
                            <div class="col-12 pt-3 border-top mt-3">
                                @php
                                    $mapEmbedUrl = $this->getMapsEmbedUrl($id);
                                @endphp
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-primary d-flex align-items-center gap-2">
                                            <i class="bi bi-geo-alt-fill text-danger fs-5"></i>
                                            <span>Lokasi & Peta Google Maps</span>
                                        </h6>
                                        <small class="text-muted">Atur koordinat GPS atau kode embed peta untuk ditampilkan di website</small>
                                    </div>
                                    @if (!empty($lembaga['lat']) && !empty($lembaga['lon']))
                                        <a href="https://www.google.com/maps?q={{ $lembaga['lat'] }},{{ $lembaga['lon'] }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-none">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                            <span>Buka di Google Maps</span>
                                        </a>
                                    @endif
                                </div>

                                <div class="row g-3">
                                    {{-- Latitude --}}
                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-compass me-1"></i>Latitude
                                        </label>
                                        <input type="text"
                                               wire:model.live.debounce.500ms="lembaga_items.{{ $id }}.lat"
                                               class="form-control form-control-sm"
                                               placeholder="Contoh: -7.536065">
                                        <div class="form-text text-muted" style="font-size: 0.725rem;">Garis Lintang (-90 s/d 90)</div>
                                    </div>

                                    {{-- Longitude --}}
                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-compass me-1"></i>Longitude
                                        </label>
                                        <input type="text"
                                               wire:model.live.debounce.500ms="lembaga_items.{{ $id }}.lon"
                                               class="form-control form-control-sm"
                                               placeholder="Contoh: 110.592812">
                                        <div class="form-text text-muted" style="font-size: 0.725rem;">Garis Bujur (-180 s/d 180)</div>
                                    </div>

                                    {{-- Maps Embed HTML / URL --}}
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-code-slash me-1"></i>Google Maps Embed (Iframe / URL)</span>
                                            <span class="badge bg-light text-muted border">Opsional</span>
                                        </label>
                                        <input type="text"
                                               wire:model.live.debounce.500ms="lembaga_items.{{ $id }}.maps_embed"
                                               class="form-control form-control-sm"
                                               placeholder='Paste kode <iframe>...</iframe> atau URL embed'>
                                        <div class="form-text text-muted" style="font-size: 0.725rem;">
                                            Kode <code>&lt;iframe&gt;</code> dari Google Maps (Bagikan &gt; Sematkan peta) atau URL langsung.
                                        </div>
                                    </div>

                                    {{-- Live Preview Embed Map --}}
                                    <div class="col-12">
                                        <div class="card border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-none">
                                            <div class="card-header bg-slate-50 dark:bg-slate-800/90 py-2.5 px-3 border-b border-slate-200 dark:border-slate-700 d-flex align-items-center justify-content-between">
                                                <span class="small fw-bold text-slate-700 dark:text-slate-300 d-flex align-items-center gap-1.5">
                                                    <i class="bi bi-map me-1 text-sky-500 dark:text-cyan-400"></i>Pratinjau Map Embed di Website
                                                </span>
                                                @if ($mapEmbedUrl)
                                                    <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 rounded-md">
                                                        <i class="bi bi-check-circle me-1"></i>Peta Terhubung
                                                    </span>
                                                @else
                                                    <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-md">
                                                        <i class="bi bi-dash-circle me-1"></i>Belum Ada Peta
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="card-body p-0 position-relative bg-slate-100/50 dark:bg-slate-900/60" style="min-height: 220px;">
                                                @if ($mapEmbedUrl)
                                                    <iframe src="{{ $mapEmbedUrl }}"
                                                            width="100%"
                                                            height="240"
                                                            style="border:0; display:block;"
                                                            allowfullscreen=""
                                                            loading="lazy"
                                                            referrerpolicy="no-referrer-when-downgrade">
                                                    </iframe>
                                                @else
                                                    <div class="p-4 text-center text-muted d-flex flex-column align-items-center justify-content-center" style="min-height: 220px;">
                                                        <i class="bi bi-geo-alt display-6 text-muted mb-2 opacity-50"></i>
                                                        <div class="fw-semibold">Pratinjau peta belum tersedia</div>
                                                        <div class="small mt-1 text-muted" style="max-width: 460px;">
                                                            Masukkan <strong>Latitude & Longitude</strong> atau tempelkan <strong>kode iframe Google Maps</strong> di atas untuk memunculkan peta interaktif.
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BAGIAN LOGO, KOP SURAT, TTD & STEMPEL --}}
                            <div class="col-12 pt-3 border-top mt-3">
                                <div class="row g-4">
                                    {{-- Logo Lembaga --}}
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-image me-1"></i>Logo Lembaga</span>
                                            <span wire:loading wire:target="logos.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded-xl border p-1 bg-slate-100 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 70px; height: 70px; overflow: hidden;">
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
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-file-earmark-richtext me-1"></i>Kop Surat</span>
                                            <span wire:loading wire:target="kops.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded-xl border p-1 bg-slate-100 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 90px; height: 70px; overflow: hidden;">
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
                                                        <i class="bi bi-x-circle me-1"></i>Hapus Kop
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- TTD Kepala Lembaga --}}
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-pen me-1"></i>TTD Kepala</span>
                                            <span wire:loading wire:target="ttds.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded-xl border p-1 d-flex align-items-center justify-content-center flex-shrink-0 border-slate-200 dark:border-slate-700"
                                                 style="width: 80px; height: 70px; overflow: hidden; background: repeating-conic-gradient(#cbd5e1 0% 25%, #f1f5f9 0% 50%) 50% / 12px 12px;"
                                                 title="Pratinjau TTD (latar transparan)">
                                                @if (isset($ttds[$id]) && method_exists($ttds[$id], 'temporaryUrl'))
                                                    <img src="{{ $ttds[$id]->temporaryUrl() }}" alt="Preview TTD" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @elseif (!empty($lembaga['ttd']))
                                                    <img src="{{ asset($lembaga['ttd']) }}" alt="TTD Kepala" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @else
                                                    <i class="bi bi-pen text-muted fs-3"></i>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                                                <input type="file"
                                                       wire:model="ttds.{{ $id }}"
                                                       accept="image/png,image/jpeg,image/webp"
                                                       class="form-control form-control-sm">
                                                <div class="form-text text-muted" style="font-size: 0.725rem;">Disarankan PNG transparan</div>
                                                @error('ttds.'.$id)
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                                @if (!empty($lembaga['ttd']))
                                                    <button type="button" wire:click="removeTtd({{ $id }})" class="btn btn-link btn-sm text-danger p-0 mt-1">
                                                        <i class="bi bi-x-circle me-1"></i>Hapus TTD
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Stempel Lembaga --}}
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-patch-check me-1"></i>Stempel Lembaga</span>
                                            <span wire:loading wire:target="stempels.{{ $id }}" class="badge bg-info text-white">Mengunggah...</span>
                                        </label>
                                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                                            <div class="rounded-xl border p-1 d-flex align-items-center justify-content-center flex-shrink-0 border-slate-200 dark:border-slate-700"
                                                 style="width: 80px; height: 70px; overflow: hidden; background: repeating-conic-gradient(#cbd5e1 0% 25%, #f1f5f9 0% 50%) 50% / 12px 12px;"
                                                 title="Pratinjau Stempel (latar transparan)">
                                                @if (isset($stempels[$id]) && method_exists($stempels[$id], 'temporaryUrl'))
                                                    <img src="{{ $stempels[$id]->temporaryUrl() }}" alt="Preview Stempel" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @elseif (!empty($lembaga['stempel']))
                                                    <img src="{{ asset($lembaga['stempel']) }}" alt="Stempel Lembaga" class="img-fluid object-fit-contain" style="max-height: 60px;">
                                                @else
                                                    <i class="bi bi-patch-check text-muted fs-3"></i>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                                                <input type="file"
                                                       wire:model="stempels.{{ $id }}"
                                                       accept="image/png,image/jpeg,image/webp"
                                                       class="form-control form-control-sm">
                                                <div class="form-text text-muted" style="font-size: 0.725rem;">Disarankan PNG transparan</div>
                                                @error('stempels.'.$id)
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                                @if (!empty($lembaga['stempel']))
                                                    <button type="button" wire:click="removeStempel({{ $id }})" class="btn btn-link btn-sm text-danger p-0 mt-1">
                                                        <i class="bi bi-x-circle me-1"></i>Hapus Stempel
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
                <div class="card shadow-sm border border-slate-200/90 dark:border-cyan-500/20 p-5 text-center rounded-2xl">
                    <i class="bi bi-building-exclamation text-muted display-4 mb-3"></i>
                    <h5 class="text-muted">Belum ada data lembaga terdaftar.</h5>
                    <p class="text-muted small">Klik tombol di bawah untuk menambahkan lembaga pertama.</p>
                    <div>
                        <button type="button" wire:click="addLembaga" class="btn-logo-gradient inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white shadow-sm">
                            <i class="bi bi-plus-circle"></i> Tambah Lembaga
                        </button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endif

    {{-- KONTEN TAB 3: INTEGRASI CLIENT (SAKU-v2) --}}
    @if ($activeTab === 'api')
        <div class="card shadow-sm border border-slate-200/90 dark:border-cyan-500/20 mb-4 rounded-2xl overflow-hidden">
            <div class="card-header bg-slate-50/90 dark:bg-slate-800/90 border-b border-slate-200/90 dark:border-cyan-500/20 py-3.5 px-4">
                <h6 class="mb-0 fw-bold text-sky-600 dark:text-cyan-400 d-flex align-items-center gap-2">
                    <i class="bi bi-hdd-network"></i> Konfigurasi Simba Master & API Client (SAKU-v2)
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info d-flex align-items-start gap-3 rounded-2xl border-0 shadow-xs">
                    <i class="bi bi-info-circle-fill fs-4 mt-1"></i>
                    <div>
                        <h6 class="fw-bold mb-1">SIMBA sebagai Sistem Master</h6>
                        <p class="mb-0 small">
                            Aplikasi <strong>SAKU-v2</strong> (dan aplikasi yayasan lainnya) akan mengambil master data lembaga (nama, tingkat, kontak, logo, kop, ttd kepala, stempel) dari SIMBA melalui API berikut.
                        </p>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint Sync Lembaga</label>
                        <div class="input-group">
                            <span class="input-group-text bg-slate-100 dark:bg-slate-800 text-sky-600 dark:text-cyan-400 fw-bold border-slate-200 dark:border-slate-700">GET</span>
                            <input type="text"
                                   readonly
                                   class="form-control font-monospace bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700"
                                   value="{{ url('/api/lembaga/sync') }}">
                        </div>
                        <small class="text-muted">Gunakan header <code>Authorization: Bearer &lt;Token&gt;</code> saat memanggil API ini.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint Sync Siswa & Rombel</label>
                        <div class="input-group">
                            <span class="input-group-text bg-slate-100 dark:bg-slate-800 text-sky-600 dark:text-cyan-400 fw-bold border-slate-200 dark:border-slate-700">GET</span>
                            <input type="text"
                                   readonly
                                   class="form-control font-monospace bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700"
                                   value="{{ url('/api/siswa/sync') }}">
                        </div>
                        <small class="text-muted">API sinkronisasi siswa aktif ke SAKU-v2.</small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Secret Key / Bearer Token SAKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700"><i class="bi bi-key-fill text-warning"></i></span>
                            <input type="text"
                                   readonly
                                   class="form-control font-monospace bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700"
                                   value="{{ config('local.saku.key', env('SAKU_KEY', 'secret-token-123')) }}">
                        </div>
                        <small class="text-muted">Dikonfigurasi di file <code>.env</code> dengan variabel <code>SAKU_KEY</code> atau <code>config/local.php</code>.</small>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
