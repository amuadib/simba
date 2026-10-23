<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.welcome')] class extends Component {
    public $lembagaUtama = [];
    public $lembagaLain = [];
    public $stats = [];
    public $filterJenjang = 'all';

    public function mount()
    {
        $all = Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];
        $cfg99 = config('local.lembaga.99') ?? [];
        
        // 1. Ambil Lembaga Utama (ID 99 - Yayasan)
        if (isset($all[99])) {
            $this->lembagaUtama = $all[99];
            $this->lembagaUtama['id'] = 99;
        } elseif (isset($all['99'])) {
            $this->lembagaUtama = $all['99'];
            $this->lembagaUtama['id'] = 99;
        } else {
            $this->lembagaUtama = $cfg99;
            $this->lembagaUtama['id'] = 99;
        }

        $this->lembagaUtama = array_merge([
            'nama' => $cfg99['nama'] ?? 'Yayasan',
            'singkatan' => $cfg99['singkatan'] ?? '',
            'npsn' => $cfg99['npsn'] ?? '',
            'nomor_statistik' => $cfg99['nomor_statistik'] ?? $cfg99['nsm'] ?? $cfg99['nss'] ?? '',
            'akreditasi' => $cfg99['akreditasi'] ?? '',
            'website' => $cfg99['website'] ?? '',
            'alamat' => $cfg99['alamat'] ?? '',
            'kontak' => $cfg99['kontak'] ?? '',
            'telp' => $cfg99['telp'] ?? '',
            'email' => $cfg99['email'] ?? '',
            'kepala' => $cfg99['kepala'] ?? '',
            'jam_kerja' => $cfg99['jam_kerja'] ?? ['Setiap hari 24/7'],
            'maps_embed' => $cfg99['maps_embed'] ?? '',
            'lat' => $cfg99['lat'] ?? '',
            'lon' => $cfg99['lon'] ?? '',
            'id' => 99,
        ], $this->lembagaUtama);
        $this->lembagaUtama['nomor_statistik'] = $this->lembagaUtama['nomor_statistik'] ?? $this->lembagaUtama['nsm'] ?? $this->lembagaUtama['nss'] ?? '';
        
        // 2. Ambil Satuan Pendidikan Lainnya (ID != 99)
        $this->lembagaLain = [];
        foreach ($all as $id => $item) {
            if ((int)$id !== 99) {
                $item = array_merge([
                    'nama' => '',
                    'singkatan' => '',
                    'npsn' => '',
                    'nomor_statistik' => '',
                    'akreditasi' => '',
                    'website' => '',
                    'alamat' => '',
                    'kontak' => '',
                    'telp' => '',
                    'email' => '',
                    'kepala' => '',
                    'jam_kerja' => null,
                    'maps_embed' => '',
                    'lat' => '',
                    'lon' => '',
                ], $item);
                $item['nomor_statistik'] = $item['nomor_statistik'] ?? $item['nsm'] ?? $item['nss'] ?? '';

                $item['id'] = (int)$id;
                $item['total_siswa'] = Siswa::where('status', 1)->where(function ($q) use ($id) {
                    $q->where('lembaga_id', $id)
                      ->orWhereHas('rombel', fn($rq) => $rq->where('lembaga_id', $id));
                })->count();
                $item['total_pegawai'] = Pegawai::where('status', 1)->where(function ($q) use ($id) {
                    $q->where('lembaga_id', $id)
                      ->orWhereHas('penugasanAktif', fn($sq) => $sq->where('lembaga_id', $id));
                })->count();

                $singkatan = strtoupper($item['singkatan'] ?? '');
                $nama = strtoupper($item['nama'] ?? '');

                if (str_contains($singkatan, 'SD') || str_contains($nama, 'SD')) {
                    $item['kategori'] = 'sd';
                    $item['label_tingkat'] = 'Tingkat Dasar (Kelas 1 - 6)';
                    $item['badge_color'] = 'primary';
                    $item['icon'] = 'bi-book-half';
                } elseif (str_contains($singkatan, 'SMP') || str_contains($nama, 'SMP')) {
                    $item['kategori'] = 'smp';
                    $item['label_tingkat'] = 'Menengah Pertama (Kelas 7 - 9)';
                    $item['badge_color'] = 'info';
                    $item['icon'] = 'bi-mortarboard-fill';
                } elseif (str_contains($singkatan, 'SMA') || str_contains($nama, 'SMA')) {
                    $item['kategori'] = 'sma';
                    $item['label_tingkat'] = 'Menengah Atas (Kelas 10 - 12)';
                    $item['badge_color'] = 'success';
                    $item['icon'] = 'bi-award-fill';
                } else {
                    $item['kategori'] = 'lain';
                    $item['label_tingkat'] = 'Satuan Pendidikan';
                    $item['badge_color'] = 'secondary';
                    $item['icon'] = 'bi-buildings-fill';
                }

                $this->lembagaLain[$id] = $item;
            }
        }

        ksort($this->lembagaLain, SORT_NUMERIC);

        // Agregat Statistik
        $this->stats = [
            'total_unit' => count($this->lembagaLain),
            'total_siswa' => Siswa::where('status', 1)->count(),
            'total_pegawai' => Pegawai::where('status', 1)->count(),
        ];
    }

    public function getLogo($item)
    {
        $logo = $item['logo'] ?? null;
        if ($logo) {
            if (Storage::disk('public')->exists($logo)) {
                return asset('storage/' . $logo);
            }
            if (file_exists(public_path($logo))) {
                return asset($logo);
            }
        }
        return logo_url();
    }

    public function formatWaUrl($phone, $namaLembaga = '')
    {
        if (empty($phone)) return null;
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }
        $pesan = "Assalamu'alaikum, saya ingin mendapatkan informasi mengenai " . ($namaLembaga ?: 'lembaga');
        return 'https://wa.me/' . $clean . '?text=' . rawurlencode($pesan);
    }
};

?>

<div class="welcome-container pb-5">

    {{-- HERO SECTION: LEMBAGA DENGAN ID 99 (YAYASAN PENAUNG) --}}
    <section class="py-5" id="profil-yayasan">
        <div class="container">
            <div class="featured-hero-card hero-animated p-4 p-md-5 mb-5">
                <div class="row align-items-center g-4">
                    {{-- Header Badges & Identity --}}
                    <div class="col-lg-8">

                        {{-- Nama Lembaga 99 --}}
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="institution-logo-container">
                                <img src="{{ $this->getLogo($lembagaUtama) }}" alt="Logo Yayasan" class="institution-logo-img" onerror="this.onerror=null; this.src='{{ asset('logo.jpg') }}';">
                            </div>
                            <div>
                                <h1 class="display-6 fw-bold mb-1 text-gradient">
                                    {{ $lembagaUtama['nama'] ?? config('local.lembaga.99.nama', 'Yayasan') }}
                                </h1>
                                <p class="text-muted mb-0 fw-medium">
                                    {{ config('local.app.keterangan') ?? (setting('nama_aplikasi') ?? 'Penyelenggara Lembaga Pendidikan Miftahul Ulum') }}
                                </p>
                                @if (!empty($lembagaUtama['nomor_statistik']) || !empty($lembagaUtama['npsn']))
                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                        @if (!empty($lembagaUtama['npsn']))
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-0.5 font-monospace" style="font-size: 0.75rem;">
                                                NPSN: {{ $lembagaUtama['npsn'] }}
                                            </span>
                                        @endif
                                        @if (!empty($lembagaUtama['nomor_statistik']))
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-0.5 font-monospace" style="font-size: 0.75rem;">
                                                NS: {{ $lembagaUtama['nomor_statistik'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Tagline & Visi Singkat --}}
                        <p class="lead fs-6 text-secondary mb-4 pe-lg-4" style="line-height: 1.7;">
                            Mewujudkan ekosistem pendidikan Islam berkarakter yang berkesinambungan
                            @if (!empty($lembagaLain))
                                melalui {{ implode(', ', array_filter(array_column($lembagaLain, 'singkatan'))) }}
                            @endif
                            untuk melahirkan generasi berakhlakul karimah, berilmu amaliyah, dan berdaya saing global.
                        </p>

                        {{-- Profil Informasi Yayasan (Alamat, Kontak, Pimpinan) --}}
                        <div class="row g-3 mb-4">
                            @if (!empty($lembagaUtama['kepala']))
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                        <div class="icon-circle icon-circle-sm bg-primary-subtle text-primary">
                                            <i class="bi bi-person-badge fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-semibold">Ketua / Pimpinan Yayasan</div>
                                            <div class="fw-bold text-heading">{{ $lembagaUtama['kepala'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($lembagaUtama['kontak']) || !empty($lembagaUtama['telp']))
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                        <div class="icon-circle icon-circle-sm bg-success-subtle text-success">
                                            <i class="bi bi-telephone-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-semibold">Pusat Informasi & Kontak</div>
                                            <div class="fw-bold text-heading">
                                                {{ $lembagaUtama['kontak'] ?: $lembagaUtama['telp'] }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($lembagaUtama['alamat']))
                                <div class="col-sm-8">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-3 h-100" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                        <div class="icon-circle icon-circle-sm bg-danger-subtle text-danger mt-1">
                                            <i class="bi bi-geo-alt-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-semibold">Alamat Kantor Yayasan</div>
                                            <div class="fw-medium text-heading small">{{ $lembagaUtama['alamat'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="col-sm-4">
                                <div class="d-flex align-items-start gap-3 p-3 rounded-3 h-100" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                    <div class="icon-circle icon-circle-sm bg-warning-subtle text-warning mt-1">
                                        <i class="bi bi-clock-history fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-semibold">Layanan Administrasi</div>
                                        <div class="fw-medium text-heading small">
                                            @if (is_array($lembagaUtama['jam_kerja'] ?? null))
                                                {{ implode(', ', $lembagaUtama['jam_kerja']) }}
                                            @else
                                                {{ $lembagaUtama['jam_kerja'] ?? 'Setiap hari 24/7' }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-wrap gap-2 pt-1">
                            <a href="#satuan-pendidikan" class="btn-primary-custom">
                                <i class="bi bi-grid-fill"></i>
                                <span>Lihat Unit Satuan Pendidikan</span>
                            </a>

                            @php
                                $waUrl = $this->formatWaUrl($lembagaUtama['kontak'] ?: $lembagaUtama['telp'] ?? null, $lembagaUtama['nama'] ?? 'Yayasan');
                            @endphp
                            @if ($waUrl)
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <span>Hubungi Yayasan</span>
                                </a>
                            @endif

                            @if (!empty($lembagaUtama['website']))
                                <a href="{{ str_starts_with($lembagaUtama['website'], 'http') ? $lembagaUtama['website'] : 'https://' . $lembagaUtama['website'] }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <i class="bi bi-globe text-primary"></i>
                                    <span>Website Yayasan</span>
                                </a>
                            @endif

                            @if (!empty($lembagaUtama['lat']) && !empty($lembagaUtama['lon']))
                                <a href="https://www.google.com/maps?q={{ $lembagaUtama['lat'] }},{{ $lembagaUtama['lon'] }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <i class="bi bi-map-fill text-primary"></i>
                                    <span>Peta Lokasi</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Metrics & Impact Card --}}
                    <div class="col-lg-4">
                        <div class="p-4 rounded-4" style="background: var(--card-bg-solid); border: 1px solid var(--card-border); box-shadow: var(--card-shadow);">
                            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom" style="border-color: var(--card-border) !important;">
                                <div>
                                    <h6 class="fw-bold mb-0 text-heading">Ringkasan Lembaga</h6>
                                    <small class="text-muted">Data terintegrasi SIMBA</small>
                                </div>
                                <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 small">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem;"></i> Aktif
                                </span>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                {{-- Total Unit Satuan Pendidikan --}}
                                <div class="metric-pill">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="metric-value text-primary">{{ $stats['total_unit'] ?? 3 }}</div>
                                            <div class="metric-label">Satuan Pendidikan</div>
                                        </div>
                                        <div class="icon-circle icon-circle-md bg-primary-subtle text-primary">
                                            <i class="bi bi-buildings-fill fs-4"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">{{ implode(', ', array_filter(array_column($lembagaLain, 'singkatan'))) ?: (config('local.app.singkatan') ?? 'SIMBA') }}</small>
                                </div>

                                {{-- Total Peserta Didik --}}
                                <div class="metric-pill">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="metric-value text-success">{{ $stats['total_siswa'] ?? 0 }}</div>
                                            <div class="metric-label">Peserta Didik Terdaftar</div>
                                        </div>
                                        <div class="icon-circle icon-circle-md bg-success-subtle text-success">
                                            <i class="bi bi-people-fill fs-4"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Siswa aktif di seluruh jenjang</small>
                                </div>

                                {{-- Total Pendidik & Pegawai --}}
                                <div class="metric-pill">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="metric-value text-warning">{{ $stats['total_pegawai'] ?? 0 }}</div>
                                            <div class="metric-label">Tenaga Pendidik & Staf</div>
                                        </div>
                                        <div class="icon-circle icon-circle-md bg-warning-subtle text-warning">
                                            <i class="bi bi-person-workspace fs-4"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Guru & staf kependidikan berdedikasi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION: LEMBAGA-LEMBAGA LAIN DI BAWAHNYA (SATUAN PENDIDIKAN) --}}
    <section class="py-5" id="satuan-pendidikan">
        <div class="container">
            {{-- Section Heading --}}
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 mb-2">
                        <span class="badge-subtle-primary">
                            <i class="bi bi-diagram-3-fill"></i> Satuan Pendidikan Naungan
                        </span>
                    </div>
                    <h2 class="h2 fw-bold text-heading mb-2">
                        Lembaga Pendidikan Di Bawah Naungan Yayasan
                    </h2>
                    <p class="text-muted mb-0">
                        Jenjang pendidikan formal yang terintegrasi secara holistik untuk membina potensi akademik, spiritual, dan kepribadian siswa.
                    </p>
                </div>

                {{-- Filter Buttons --}}
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="btn-group p-1 rounded-pill" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                        <button type="button" wire:click="$set('filterJenjang', 'all')" class="btn btn-sm rounded-pill px-3 fw-semibold {{ $filterJenjang === 'all' ? 'btn-primary shadow-sm' : 'btn-link text-decoration-none text-muted' }}">
                            Semua ({{ count($lembagaLain) }})
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'sd')" class="btn btn-sm rounded-pill px-3 fw-semibold {{ $filterJenjang === 'sd' ? 'btn-primary shadow-sm' : 'btn-link text-decoration-none text-muted' }}">
                            SD
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'smp')" class="btn btn-sm rounded-pill px-3 fw-semibold {{ $filterJenjang === 'smp' ? 'btn-primary shadow-sm' : 'btn-link text-decoration-none text-muted' }}">
                            SMP
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'sma')" class="btn btn-sm rounded-pill px-3 fw-semibold {{ $filterJenjang === 'sma' ? 'btn-primary shadow-sm' : 'btn-link text-decoration-none text-muted' }}">
                            SMA
                        </button>
                    </div>
                </div>
            </div>

            {{-- Grid Cards Lembaga Lain --}}
            <div class="row g-4" x-data="{ activeModal: null }">
                @forelse ($lembagaLain as $item)
                    @if ($filterJenjang !== 'all' && ($item['kategori'] ?? '') !== $filterJenjang)
                        @continue
                    @endif

                    <div class="col-lg-4 col-md-6">
                        <div class="unit-card glass-card">
                            <div>
                                {{-- Card Header: Logo, Badges & Name --}}
                                <div class="unit-card-header">
                                    <div class="unit-logo">
                                        <img src="{{ $this->getLogo($item) }}" alt="{{ $item['singkatan'] ?? 'Logo' }}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='{{ asset('logo.jpg') }}';">
                                    </div>
                                    <div class="flex-fill">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <span class="badge bg-{{ $item['badge_color'] }}-subtle text-{{ $item['badge_color'] }} rounded-pill px-2.5 py-0.5 fw-bold" style="font-size: 0.75rem;">
                                                <i class="bi {{ $item['icon'] }} me-1"></i> {{ $item['singkatan'] ?? 'UNIT' }}
                                            </span>
                                            @if (!empty($item['akreditasi']))
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.725rem;">
                                                    <i class="bi bi-patch-check-fill text-warning me-1"></i> Akreditasi {{ $item['akreditasi'] }}
                                                </span>
                                            @endif
                                            @if (!empty($item['npsn']))
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 font-monospace" style="font-size: 0.725rem;">
                                                    NPSN: {{ $item['npsn'] }}
                                                </span>
                                            @endif
                                            @if (!empty($item['nomor_statistik']))
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 font-monospace" style="font-size: 0.725rem;">
                                                    NS: {{ $item['nomor_statistik'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <h5 class="fw-bold mb-0 text-heading line-clamp-1" title="{{ $item['nama'] }}">
                                            {{ $item['nama'] }}
                                        </h5>
                                        <small class="text-muted d-block mt-0.5">
                                            {{ $item['label_tingkat'] }}
                                        </small>
                                    </div>
                                </div>

                                {{-- Stats Bar for Unit --}}
                                <div class="d-flex align-items-center justify-content-between p-2.5 px-3 rounded-3 mb-3" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-people-fill text-primary"></i>
                                        <span class="small text-muted">Siswa:</span>
                                        <span class="fw-bold text-heading">{{ $item['total_siswa'] ?? 0 }}</span>
                                    </div>
                                    <div class="vr opacity-25"></div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-person-badge-fill text-warning"></i>
                                        <span class="small text-muted">Guru & Staf:</span>
                                        <span class="fw-bold text-heading">{{ $item['total_pegawai'] ?? 0 }}</span>
                                    </div>
                                </div>

                                {{-- Info List --}}
                                <ul class="unit-info-list">
                                    @if (!empty($item['kepala']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-person-check-fill text-primary"></i>
                                            <div>
                                                <span class="text-muted small d-block">Kepala Sekolah:</span>
                                                <span class="fw-semibold text-heading">{{ $item['kepala'] }}</span>
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($item['alamat']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-geo-alt-fill text-danger"></i>
                                            <div>
                                                <span class="text-muted small d-block">Alamat:</span>
                                                <span class="small text-heading">{{ $item['alamat'] }}</span>
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($item['jam_kerja']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-clock-fill text-warning"></i>
                                            <div>
                                                <span class="text-muted small d-block">Jam Pelayanan:</span>
                                                <span class="small text-heading">
                                                    @if (is_array($item['jam_kerja']))
                                                        {{ $item['jam_kerja'][0] ?? '-' }}
                                                    @else
                                                        {{ $item['jam_kerja'] }}
                                                    @endif
                                                </span>
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($item['kontak']) || !empty($item['telp']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-telephone-fill text-success"></i>
                                            <div>
                                                <span class="text-muted small d-block">Kontak / Narahubung:</span>
                                                <span class="fw-semibold text-heading small">
                                                    {{ $item['kontak'] ?: $item['telp'] }}
                                                </span>
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($item['email']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-envelope-fill text-info"></i>
                                            <div>
                                                <span class="text-muted small d-block">Email:</span>
                                                <span class="small text-heading">{{ $item['email'] }}</span>
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($item['website']))
                                        <li class="unit-info-item">
                                            <i class="bi bi-globe text-primary"></i>
                                            <div>
                                                <span class="text-muted small d-block">Website Resmi:</span>
                                                <a href="{{ str_starts_with($item['website'], 'http') ? $item['website'] : 'https://' . $item['website'] }}" target="_blank" rel="noopener" class="small text-decoration-none fw-semibold text-primary">
                                                    {{ $item['website'] }} <i class="bi bi-box-arrow-up-right ms-0.5" style="font-size: 0.7rem;"></i>
                                                </a>
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </div>

                            {{-- Card Footer Actions --}}
                            <div class="pt-3 border-top d-flex align-items-center gap-2" style="border-color: var(--card-border) !important;">
                                @php
                                    $unitWa = $this->formatWaUrl($item['telp'] ?: $item['kontak'] ?? null, $item['nama']);
                                @endphp

                                @if ($unitWa)
                                    <a href="{{ $unitWa }}" target="_blank" rel="noopener" class="btn btn-action-card btn-action-card-wa flex-fill">
                                        <i class="bi bi-whatsapp"></i> Chat WA
                                    </a>
                                @endif

                                <button type="button" class="btn btn-action-card btn-action-card-detail flex-fill" @click="activeModal = {{ $item['id'] }}">
                                    <i class="bi bi-info-circle"></i> Detail
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Detail Lembaga via Alpine.js --}}
                    <div x-show="activeModal === {{ $item['id'] }}" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         @keydown.escape.window="activeModal = null"
                         style="position: fixed; inset: 0; z-index: 1055; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 1rem;"
                         x-cloak>
                        
                        {{-- Backdrop --}}
                        <div style="position: fixed; inset: 0; background: rgba(5, 14, 26, 0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" @click="activeModal = null"></div>

                        {{-- Modal Box --}}
                        <div class="glass-card p-3 shadow-lg rounded-4" 
                             style="position: relative; width: 100%; max-width: 800px; background: var(--card-bg-solid); border: 1px solid var(--card-border); z-index: 1056; max-height: 90vh; overflow-y: auto;"
                             @click.stop>
                            <div class="modal-header border-0 pb-0">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="unit-logo" style="width: 52px; height: 52px;">
                                        <img src="{{ $this->getLogo($item) }}" alt="{{ $item['singkatan'] ?? 'Logo' }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                    </div>
                                    <div>
                                        <h5 class="modal-title fw-bold text-heading" id="modalLabel{{ $item['id'] }}">
                                            {{ $item['nama'] }}
                                        </h5>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                            <span class="badge bg-{{ $item['badge_color'] }}-subtle text-{{ $item['badge_color'] }} rounded-pill small">
                                                {{ $item['label_tingkat'] }}
                                            </span>
                                            @if (!empty($item['akreditasi']))
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill small fw-bold">
                                                    <i class="bi bi-patch-check-fill text-warning me-1"></i> Terakreditasi {{ $item['akreditasi'] }}
                                                </span>
                                            @endif
                                            @if (!empty($item['npsn']))
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill small font-monospace">
                                                    NPSN: {{ $item['npsn'] }}
                                                </span>
                                            @endif
                                            @if (!empty($item['nomor_statistik']))
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill small font-monospace">
                                                    NS: {{ $item['nomor_statistik'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" @click="activeModal = null" aria-label="Close"></button>
                            </div>
                            <div class="modal-body py-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-3 h-100" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                            <h6 class="fw-bold text-heading mb-3"><i class="bi bi-info-circle-fill text-primary me-2"></i>Informasi Umum</h6>
                                            @if (!empty($item['npsn']))
                                                <div class="mb-2.5">
                                                    <span class="text-muted small d-block">NPSN (Nomor Pokok Sekolah Nasional):</span>
                                                    <span class="fw-bold text-heading font-monospace">{{ $item['npsn'] }}</span>
                                                </div>
                                            @endif
                                            @if (!empty($item['nomor_statistik']))
                                                <div class="mb-2.5">
                                                    <span class="text-muted small d-block">Nomor Statistik (NSS / NSM):</span>
                                                    <span class="fw-bold text-heading font-monospace">{{ $item['nomor_statistik'] }}</span>
                                                </div>
                                            @endif
                                            @if (!empty($item['akreditasi']))
                                                <div class="mb-2.5">
                                                    <span class="text-muted small d-block">Status Akreditasi:</span>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-bold px-2 py-1">
                                                        <i class="bi bi-patch-check-fill text-warning me-1"></i> Akreditasi {{ $item['akreditasi'] }}
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="mb-2.5">
                                                <span class="text-muted small d-block">Kepala Lembaga:</span>
                                                <strong class="text-heading">{{ $item['kepala'] ?: '-' }}</strong>
                                            </div>
                                            <div class="mb-2.5">
                                                <span class="text-muted small d-block">Alamat Lengkap:</span>
                                                <span class="text-heading small">{{ $item['alamat'] ?: '-' }}</span>
                                            </div>
                                            <div class="mb-2.5">
                                                <span class="text-muted small d-block">Kontak / Telepon:</span>
                                                <span class="text-heading small">{{ $item['telp'] ?: $item['kontak'] ?: '-' }}</span>
                                            </div>
                                            <div class="mb-2.5">
                                                <span class="text-muted small d-block">Email Resmi:</span>
                                                <span class="text-heading small">{{ $item['email'] ?: '-' }}</span>
                                            </div>
                                            @if (!empty($item['website']))
                                                <div>
                                                    <span class="text-muted small d-block">Website Resmi:</span>
                                                    <a href="{{ str_starts_with($item['website'], 'http') ? $item['website'] : 'https://' . $item['website'] }}" target="_blank" rel="noopener" class="small text-primary text-decoration-none fw-semibold">
                                                        <i class="bi bi-globe me-1"></i> {{ $item['website'] }} <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="p-3 rounded-3 h-100" style="background: var(--bg-page-subtle); border: 1px solid var(--card-border);">
                                            <h6 class="fw-bold text-heading mb-3"><i class="bi bi-clock-fill text-warning me-2"></i>Jadwal & Jam Operasional</h6>
                                            @if (is_array($item['jam_kerja'] ?? null))
                                                <ul class="list-unstyled mb-0 small">
                                                    @foreach ($item['jam_kerja'] as $jam)
                                                        <li class="mb-1.5 d-flex align-items-center gap-2">
                                                            <i class="bi bi-check2 text-success"></i>
                                                            <span class="text-heading">{{ $jam }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="text-heading small">{{ $item['jam_kerja'] ?? 'Senin - Sabtu' }}</div>
                                            @endif

                                            <hr class="my-3 opacity-25">

                                            <h6 class="fw-bold text-heading mb-2"><i class="bi bi-bar-chart-fill text-info me-2"></i>Data Sivitas Akademika</h6>
                                            <div class="d-flex gap-3">
                                                <div>
                                                    <span class="text-muted small d-block">Total Siswa:</span>
                                                    <span class="fs-5 fw-bold text-primary">{{ $item['total_siswa'] ?? 0 }}</span>
                                                </div>
                                                <div class="vr opacity-25"></div>
                                                <div>
                                                    <span class="text-muted small d-block">Guru & Pegawai:</span>
                                                    <span class="fs-5 fw-bold text-warning">{{ $item['total_pegawai'] ?? 0 }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Google Maps Embed if available --}}
                                    @if (!empty($item['maps_embed']))
                                        <div class="col-12 mt-3">
                                            <div class="rounded-3 overflow-hidden" style="border: 1px solid var(--card-border); max-height: 250px;">
                                                {!! $item['maps_embed'] !!}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                @if (!empty($item['website']))
                                    <a href="{{ str_starts_with($item['website'], 'http') ? $item['website'] : 'https://' . $item['website'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary rounded-pill px-3">
                                        <i class="bi bi-globe me-1"></i> Kunjungi Website
                                    </a>
                                @endif
                                @if ($unitWa)
                                    <a href="{{ $unitWa }}" target="_blank" rel="noopener" class="btn btn-success rounded-pill px-4">
                                        <i class="bi bi-whatsapp me-1"></i> Hubungi WhatsApp
                                    </a>
                                @endif
                                <button type="button" class="btn btn-secondary rounded-pill px-4" @click="activeModal = null">Tutup</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">Belum ada data satuan pendidikan.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- SECTION: NILAI & KEUNGGULAN LEMBAGA --}}
    <section class="py-5" id="keunggulan">
        <div class="container">
            <div class="text-center max-w-3xl mx-auto mb-5">
                <span class="badge-subtle-primary mb-2">
                    <i class="bi bi-star-fill"></i> Nilai & Karakteristik
                </span>
                <h2 class="display-6 fw-bold text-heading">
                    Komitmen Keunggulan Pendidikan Terpadu
                </h2>
                <p class="text-muted">
                    Menghubungkan nilai-nilai akhlak karimah dengan penguasaan ilmu pengetahuan dan transformasi teknologi pendidikan modern.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="p-4 rounded-4 h-100 glass-card" style="border: 1px solid var(--card-border);">
                        <div class="icon-circle icon-circle-lg bg-primary-subtle text-primary mb-3">
                            <i class="bi bi-patch-check-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold text-heading mb-2">Pendidikan Berkelanjutan</h5>
                        <p class="text-secondary small mb-0">
                            Jenjang pendidikan terstruktur mulai dari tingkat dasar hingga tingkat atas dalam satu ekosistem pendidikan terpadu.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-4 rounded-4 h-100 glass-card" style="border: 1px solid var(--card-border);">
                        <div class="icon-circle icon-circle-lg bg-success-subtle text-success mb-3">
                            <i class="bi bi-book-half fs-4"></i>
                        </div>
                        <h5 class="fw-bold text-heading mb-2">Nilai Keislaman & Moral</h5>
                        <p class="text-secondary small mb-0">
                            Menanamkan pembiasaan ibadah harian, adab sopan santun, hafalan Al-Qur'an, dan wawasan keagamaan Islam yang moderat.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-4 rounded-4 h-100 glass-card" style="border: 1px solid var(--card-border);">
                        <div class="icon-circle icon-circle-lg bg-info-subtle text-info mb-3">
                            <i class="bi bi-laptop-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold text-heading mb-2">Digitalisasi SIMBA</h5>
                        <p class="text-secondary small mb-0">
                            Didukung sistem informasi manajemen sekolah yang canggih untuk absensi kehadiran, rekapitulasi nilai, dan jurnal belajar mengajar.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-4 rounded-4 h-100 glass-card" style="border: 1px solid var(--card-border);">
                        <div class="icon-circle icon-circle-lg bg-warning-subtle text-warning mb-3">
                            <i class="bi bi-heart-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold text-heading mb-2">Pendidik Berdedikasi</h5>
                        <p class="text-secondary small mb-0">
                            Didampingi tenaga guru yang kompeten, berakhlak mulia, dan berorientasi pada perkembangan potensi setiap anak didik.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION: PETA LOKASI TERPADU --}}
    <section class="py-5" id="peta-lokasi">
        <div class="container">
            <div class="p-4 p-md-5 rounded-4 glass-card" style="border: 1px solid var(--card-border);">
                <div class="row align-items-center g-4">
                    <div class="col-lg-5">
                        <span class="badge-subtle-primary mb-2">
                            <i class="bi bi-geo-alt-fill"></i> Lokasi Kampus Terpadu
                        </span>
                        <h3 class="fw-bold text-heading mb-3">
                            Kawasan Pendidikan {{ $lembagaUtama['nama'] ?? 'Yayasan' }}
                        </h3>
                        <p class="text-secondary small mb-4" style="line-height: 1.7;">
                            Seluruh satuan pendidikan naungan
                            @if (!empty($lembagaLain))
                                ({{ implode(', ', array_filter(array_column($lembagaLain, 'singkatan'))) }})
                            @endif
                            dan kantor pusat berlokasi strategis, aman, dan kondusif untuk mendukung kegiatan belajar mengajar secara optimal.
                        </p>

                        @php
                            $alamatKampus = $lembagaUtama['alamat'] ?: (reset($lembagaLain)['alamat'] ?? config('local.lembaga.99.alamat') ?? '');
                            $kontakKampus = $lembagaUtama['kontak'] ?: $lembagaUtama['telp'] ?: (reset($lembagaLain)['kontak'] ?? reset($lembagaLain)['telp'] ?? config('local.lembaga.99.kontak') ?? '');
                            $emailKampus = $lembagaUtama['email'] ?: (reset($lembagaLain)['email'] ?? config('local.lembaga.99.email') ?? '');
                        @endphp

                        <div class="d-flex flex-column gap-3 mb-4">
                            @if (!empty($alamatKampus))
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-geo-alt-fill text-danger fs-5 mt-0.5"></i>
                                    <div>
                                        <div class="fw-bold text-heading small">Alamat Kampus:</div>
                                        <div class="text-muted small">{{ $alamatKampus }}</div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($kontakKampus))
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-telephone-fill text-success fs-5 mt-0.5"></i>
                                    <div>
                                        <div class="fw-bold text-heading small">Telepon / WhatsApp:</div>
                                        <div class="text-muted small">{{ $kontakKampus }}</div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($emailKampus))
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-envelope-fill text-primary fs-5 mt-0.5"></i>
                                    <div>
                                        <div class="fw-bold text-heading small">Email:</div>
                                        <div class="text-muted small">{{ $emailKampus }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @php
                            $mapsTarget = gmaps_url(99) ?: (gmaps_url(1) ?: (!empty($alamatKampus) ? 'https://maps.google.com/?q=' . urlencode($alamatKampus) : '#'));
                        @endphp
                        <a href="{{ $mapsTarget }}" target="_blank" rel="noopener" class="btn-primary-custom">
                            <i class="bi bi-compass"></i>
                            <span>Buka di Google Maps</span>
                        </a>
                    </div>

                    <div class="col-lg-7">
                        <div class="rounded-4 overflow-hidden shadow-sm" style="height: 380px; border: 1.5px solid var(--card-border);">
                            @php
                                $embedUrl = gmaps_embed_url(99) ?: (gmaps_embed_url(1) ?: null);
                            @endphp
                            @if ($embedUrl)
                                <iframe src="{{ $embedUrl }}" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            @elseif (!empty($lembagaUtama['maps_embed']))
                                {!! str_replace(['width="600"', 'height="450"'], ['width="100%"', 'height="100%"'], $lembagaUtama['maps_embed']) !!}
                            @elseif (!empty($lembagaLain[1]['maps_embed']))
                                {!! str_replace(['width="600"', 'height="450"'], ['width="100%"', 'height="100%"'], $lembagaLain[1]['maps_embed']) !!}
                            @elseif (!empty($alamatKampus))
                                <iframe src="https://maps.google.com/maps?q={{ urlencode($alamatKampus) }}&hl=id&z=15&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION: CTA PORTAL LOGIN --}}
    <section class="py-5">
        <div class="container">
            <div class="p-5 rounded-4 text-center text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 50%, #2563eb 100%); box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.45);">
                <div class="position-relative" style="z-index: 2;">
                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-1 mb-3 fw-semibold">
                        <i class="bi bi-lock-fill me-1"></i> Sistem Informasi SIMBA
                    </span>
                    <h2 class="display-6 fw-bold mb-3">
                        Portal Akademik & Presensi Pembelajaran
                    </h2>
                    <p class="lead fs-6 text-white text-opacity-75 mb-4 mx-auto" style="max-width: 650px;">
                        Kemudahan pengelolaan presensi real-time, pencatatan jurnal mengajar, input nilai, serta data kepegawaian dan kesiswaan bagi seluruh civitas lembaga.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-light btn-lg rounded-pill px-4 fw-bold shadow">
                                <i class="bi bi-speedometer2 text-primary me-2"></i> Buka Dashboard Saya
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-light btn-lg rounded-pill px-4 fw-bold shadow">
                                <i class="bi bi-box-arrow-in-right text-primary me-2"></i> Masuk Akun SIMBA
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
