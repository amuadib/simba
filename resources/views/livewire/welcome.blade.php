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
                    $item['badge_theme'] = 'sky';
                } elseif (str_contains($singkatan, 'SMP') || str_contains($nama, 'SMP')) {
                    $item['kategori'] = 'smp';
                    $item['label_tingkat'] = 'Menengah Pertama (Kelas 7 - 9)';
                    $item['badge_theme'] = 'cyan';
                } elseif (str_contains($singkatan, 'SMA') || str_contains($nama, 'SMA')) {
                    $item['kategori'] = 'sma';
                    $item['label_tingkat'] = 'Menengah Atas (Kelas 10 - 12)';
                    $item['badge_theme'] = 'emerald';
                } else {
                    $item['kategori'] = 'lain';
                    $item['label_tingkat'] = 'Satuan Pendidikan';
                    $item['badge_theme'] = 'slate';
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

<div class="welcome-container pb-12">

    {{-- HERO SECTION: LEMBAGA DENGAN ID 99 (YAYASAN PENAUNG) --}}
    <section class="py-10" id="profil-yayasan">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="featured-hero-card hero-animated p-6 md:p-8 mb-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    {{-- Header Badges & Identity --}}
                    <div class="lg:col-span-8">

                        {{-- Nama Lembaga 99 --}}
                        <div class="flex items-center gap-4 mb-4">
                            <div class="institution-logo-container flex-shrink-0">
                                <img src="{{ $this->getLogo($lembagaUtama) }}" alt="Logo Yayasan" class="institution-logo-img" onerror="this.onerror=null; this.src='{{ asset('logo.jpg') }}';">
                            </div>
                            <div>
                                <h1 class="text-2xl md:text-4xl font-extrabold mb-1 font-display text-slate-900 dark:text-white leading-tight">
                                    {{ $lembagaUtama['nama'] ?? config('local.lembaga.99.nama', 'Yayasan') }}
                                </h1>
                                <p class="text-slate-500 dark:text-slate-400 mb-0 font-medium text-xs md:text-sm">
                                    {{ config('local.app.keterangan') ?? (setting('nama_aplikasi') ?? 'Penyelenggara Lembaga Pendidikan Miftahul Ulum') }}
                                </p>
                                @if (!empty($lembagaUtama['nomor_statistik']) || !empty($lembagaUtama['npsn']))
                                    <div class="flex items-center gap-2 flex-wrap mt-2.5">
                                        @if (!empty($lembagaUtama['npsn']))
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                NPSN: {{ $lembagaUtama['npsn'] }}
                                            </span>
                                        @endif
                                        @if (!empty($lembagaUtama['nomor_statistik']))
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                NS: {{ $lembagaUtama['nomor_statistik'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Tagline & Visi Singkat --}}
                        <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 mb-6 lg:pr-6 leading-relaxed">
                            Mewujudkan ekosistem pendidikan Islam berkarakter yang berkesinambungan
                            @if (!empty($lembagaLain))
                                melalui {{ implode(', ', array_filter(array_column($lembagaLain, 'singkatan'))) }}
                            @endif
                            untuk melahirkan generasi berakhlakul karimah, berilmu amaliyah, dan berdaya saing global.
                        </p>

                        {{-- Profil Informasi Yayasan (Alamat, Kontak, Pimpinan) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 mb-6">
                            @if (!empty($lembagaUtama['kepala']))
                                <div class="sm:col-span-6">
                                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl h-full bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                        <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" /></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-0.5">Ketua / Pimpinan Yayasan</div>
                                            <div class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate">{{ $lembagaUtama['kepala'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($lembagaUtama['kontak']) || !empty($lembagaUtama['telp']))
                                <div class="sm:col-span-6">
                                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl h-full bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-0.5">Pusat Informasi & Kontak</div>
                                            <div class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate">
                                                {{ $lembagaUtama['kontak'] ?: $lembagaUtama['telp'] }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($lembagaUtama['alamat']))
                                <div class="sm:col-span-8">
                                    <div class="flex items-start gap-3.5 p-3.5 rounded-2xl h-full bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                        </div>
                                        <div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-0.5">Alamat Kantor Yayasan</div>
                                            <div class="font-medium text-slate-800 dark:text-slate-200 text-xs md:text-sm leading-snug">{{ $lembagaUtama['alamat'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="sm:col-span-4">
                                <div class="flex items-start gap-3.5 p-3.5 rounded-2xl h-full bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-0.5">Layanan Administrasi</div>
                                        <div class="font-medium text-slate-800 dark:text-slate-200 text-xs md:text-sm">
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
                        <div class="flex flex-wrap items-center gap-2.5 pt-1">
                            <a href="#satuan-pendidikan" class="btn-primary-custom">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
                                <span>Lihat Unit Satuan Pendidikan</span>
                            </a>

                            @php
                                $waUrl = $this->formatWaUrl($lembagaUtama['kontak'] ?: $lembagaUtama['telp'] ?? null, $lembagaUtama['nama'] ?? 'Yayasan');
                            @endphp
                            @if ($waUrl)
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M4.848 2.771A49.144 49.144 0 0 1 12 2.25c2.43 0 4.817.177 7.152.52 1.978.292 3.348 2.024 3.348 3.97v6.02c0 1.946-1.37 3.678-3.348 3.97a48.901 48.901 0 0 1-3.476.383.39.39 0 0 0-.297.17l-2.755 4.133a.75.75 0 0 1-1.248 0l-2.755-4.133a.39.39 0 0 0-.297-.17 48.9 48.9 0 0 1-3.476-.384c-1.978-.29-3.348-2.024-3.348-3.97V6.741c0-1.946 1.37-3.68 3.348-3.97Z" clip-rule="evenodd" /></svg>
                                    <span>Hubungi Yayasan</span>
                                </a>
                            @endif

                            @if (!empty($lembagaUtama['website']))
                                <a href="{{ str_starts_with($lembagaUtama['website'], 'http') ? $lembagaUtama['website'] : 'https://' . $lembagaUtama['website'] }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                    <span>Website Yayasan</span>
                                </a>
                            @endif

                            @if (!empty($lembagaUtama['lat']) && !empty($lembagaUtama['lon']))
                                <a href="https://www.google.com/maps?q={{ $lembagaUtama['lat'] }},{{ $lembagaUtama['lon'] }}" target="_blank" rel="noopener" class="btn-outline-custom">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                    <span>Peta Lokasi</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Metrics & Impact Card --}}
                    <div class="lg:col-span-4">
                        <div class="p-5 md:p-6 rounded-3xl bg-slate-50/90 dark:bg-slate-900/90 border border-slate-200/90 dark:border-slate-800 shadow-lg">
                            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-slate-200/80 dark:border-slate-800">
                                <div>
                                    <h6 class="font-bold text-sm text-slate-900 dark:text-slate-100 mb-0 font-display">Ringkasan Lembaga</h6>
                                    <small class="text-slate-500 dark:text-slate-400 text-xs">Data terintegrasi SIMBA</small>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Aktif
                                </span>
                            </div>

                            <div class="flex flex-col gap-3.5">
                                {{-- Total Unit Satuan Pendidikan --}}
                                <div class="metric-pill p-3.5 rounded-2xl bg-white dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 font-display">{{ $stats['total_unit'] ?? 3 }}</div>
                                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-0.5">Satuan Pendidikan</div>
                                        </div>
                                        <div class="w-11 h-11 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M3 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5H15v-18a.75.75 0 0 0 0-1.5H3Zm3 3a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5A.75.75 0 0 1 6 6.75v-1.5Zm0 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5A.75.75 0 0 1 6 11.25v-1.5Zm0 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5Zm4.5-9a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5Zm0 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5Zm0 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5ZM16.5 6.75A.75.75 0 0 1 17.25 6h4.5a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1-.75-.75V6.75Zm1.5 1.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5Zm0 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-1.5Z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </div>
                                    <small class="text-slate-500 dark:text-slate-400 block mt-2 text-xs truncate">{{ implode(', ', array_filter(array_column($lembagaLain, 'singkatan'))) ?: (config('local.app.singkatan') ?? 'SIMBA') }}</small>
                                </div>

                                {{-- Total Peserta Didik --}}
                                <div class="metric-pill p-3.5 rounded-2xl bg-white dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-display">{{ $stats['total_siswa'] ?? 0 }}</div>
                                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-0.5">Peserta Didik Terdaftar</div>
                                        </div>
                                        <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.66.75.75 0 0 0 .364-.632l.001-.12c0-3.179-2.576-5.757-5.756-5.757a6.71 6.71 0 0 0-1.84.257 8.625 8.625 0 0 1 2.405 6.808Z" /></svg>
                                        </div>
                                    </div>
                                    <small class="text-slate-500 dark:text-slate-400 block mt-2 text-xs">Siswa aktif di seluruh jenjang</small>
                                </div>

                                {{-- Total Pendidik & Pegawai --}}
                                <div class="metric-pill p-3.5 rounded-2xl bg-white dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-2xl font-extrabold text-amber-500 dark:text-amber-400 font-display">{{ $stats['total_pegawai'] ?? 0 }}</div>
                                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-0.5">Tenaga Pendidik & Staf</div>
                                        </div>
                                        <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </div>
                                    <small class="text-slate-500 dark:text-slate-400 block mt-2 text-xs">Guru & staf kependidikan berdedikasi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION: LEMBAGA-LEMBAGA LAIN DI BAWAHNYA (SATUAN PENDIDIKAN) --}}
    <section class="py-12" id="satuan-pendidikan">
        <div class="container mx-auto px-4 max-w-7xl">
            {{-- Section Heading --}}
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 mb-2.5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg> Satuan Pendidikan Naungan
                        </span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold font-display text-slate-900 dark:text-slate-100 mb-2">
                        Lembaga Pendidikan Di Bawah Naungan Yayasan
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-0 leading-relaxed">
                        Jenjang pendidikan formal yang terintegrasi secara holistik untuk membina potensi akademik, spiritual, dan kepribadian siswa.
                    </p>
                </div>

                {{-- Filter Buttons --}}
                <div class="flex-shrink-0">
                    <div class="inline-flex p-1 rounded-full bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                        <button type="button" wire:click="$set('filterJenjang', 'all')" class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all {{ $filterJenjang === 'all' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400' }}">
                            Semua ({{ count($lembagaLain) }})
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'sd')" class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all {{ $filterJenjang === 'sd' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400' }}">
                            SD
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'smp')" class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all {{ $filterJenjang === 'smp' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400' }}">
                            SMP
                        </button>
                        <button type="button" wire:click="$set('filterJenjang', 'sma')" class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all {{ $filterJenjang === 'sma' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400' }}">
                            SMA
                        </button>
                    </div>
                </div>
            </div>

            {{-- Grid Cards Lembaga Lain --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" x-data="{ activeModal: null }">
                @forelse ($lembagaLain as $item)
                    @if ($filterJenjang !== 'all' && ($item['kategori'] ?? '') !== $filterJenjang)
                        @continue
                    @endif

                    <div class="unit-card glass-card p-5 md:p-6 rounded-3xl flex flex-col justify-between h-full">
                        <div>
                            {{-- Card Header: Logo, Badges & Name --}}
                            <div class="flex items-start gap-3.5 mb-4">
                                <div class="unit-logo flex-shrink-0">
                                    <img src="{{ $this->getLogo($item) }}" alt="{{ $item['singkatan'] ?? 'Logo' }}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='{{ asset('logo.jpg') }}';">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                                        @if ($item['kategori'] === 'sd')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
                                                {{ $item['singkatan'] ?? 'UNIT' }}
                                            </span>
                                        @elseif ($item['kategori'] === 'smp')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-800">
                                                {{ $item['singkatan'] ?? 'UNIT' }}
                                            </span>
                                        @elseif ($item['kategori'] === 'sma')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                {{ $item['singkatan'] ?? 'UNIT' }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                {{ $item['singkatan'] ?? 'UNIT' }}
                                            </span>
                                        @endif

                                        @if (!empty($item['akreditasi']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> Akreditasi {{ $item['akreditasi'] }}
                                            </span>
                                        @endif
                                        @if (!empty($item['npsn']))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                                NPSN: {{ $item['npsn'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <h5 class="font-bold text-base text-slate-900 dark:text-slate-100 truncate mb-0.5" title="{{ $item['nama'] }}">
                                        {{ $item['nama'] }}
                                    </h5>
                                    <small class="text-xs text-slate-500 dark:text-slate-400 block">
                                        {{ $item['label_tingkat'] }}
                                    </small>
                                </div>
                            </div>

                            {{-- Stats Bar for Unit --}}
                            <div class="flex items-center justify-between p-3 rounded-2xl mb-4 bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.66.75.75 0 0 0 .364-.632l.001-.12c0-3.179-2.576-5.757-5.756-5.757a6.71 6.71 0 0 0-1.84.257 8.625 8.625 0 0 1 2.405 6.808Z" /></svg>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Siswa:</span>
                                    <span class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ $item['total_siswa'] ?? 0 }}</span>
                                </div>
                                <div class="w-px h-4 bg-slate-200 dark:bg-slate-700"></div>
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" /></svg>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Guru & Staf:</span>
                                    <span class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ $item['total_pegawai'] ?? 0 }}</span>
                                </div>
                            </div>

                            {{-- Info List --}}
                            <div class="space-y-2.5 mb-5 text-xs">
                                @if (!empty($item['kepala']))
                                    <div class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                        <div class="min-w-0">
                                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Kepala Sekolah:</span>
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">{{ $item['kepala'] }}</span>
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($item['alamat']))
                                    <div class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-rose-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                        <div>
                                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Alamat:</span>
                                            <span class="text-slate-700 dark:text-slate-300 leading-snug">{{ $item['alamat'] }}</span>
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($item['jam_kerja']))
                                    <div class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        <div>
                                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Jam Pelayanan:</span>
                                            <span class="text-slate-700 dark:text-slate-300">
                                                @if (is_array($item['jam_kerja']))
                                                    {{ $item['jam_kerja'][0] ?? '-' }}
                                                @else
                                                    {{ $item['jam_kerja'] }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($item['kontak']) || !empty($item['telp']))
                                    <div class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                        <div class="min-w-0">
                                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Kontak / Narahubung:</span>
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                                                {{ $item['kontak'] ?: $item['telp'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($item['website']))
                                    <div class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                        <div class="min-w-0">
                                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Website Resmi:</span>
                                            <a href="{{ str_starts_with($item['website'], 'http') ? $item['website'] : 'https://' . $item['website'] }}" target="_blank" rel="noopener" class="font-semibold text-sky-600 dark:text-sky-400 hover:underline truncate block">
                                                {{ $item['website'] }}
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer Actions --}}
                        <div class="pt-3.5 border-t border-slate-200/80 dark:border-slate-800 flex items-center gap-2 mt-auto">
                            @php
                                $unitWa = $this->formatWaUrl($item['telp'] ?: $item['kontak'] ?? null, $item['nama']);
                            @endphp

                            @if ($unitWa)
                                <a href="{{ $unitWa }}" target="_blank" rel="noopener" class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-semibold text-emerald-600 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M4.848 2.771A49.144 49.144 0 0 1 12 2.25c2.43 0 4.817.177 7.152.52 1.978.292 3.348 2.024 3.348 3.97v6.02c0 1.946-1.37 3.678-3.348 3.97a48.901 48.901 0 0 1-3.476.383.39.39 0 0 0-.297.17l-2.755 4.133a.75.75 0 0 1-1.248 0l-2.755-4.133a.39.39 0 0 0-.297-.17 48.9 48.9 0 0 1-3.476-.384c-1.978-.29-3.348-2.024-3.348-3.97V6.741c0-1.946 1.37-3.68 3.348-3.97Z" clip-rule="evenodd" /></svg> Chat WA
                                </a>
                            @endif

                            <button type="button" class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-semibold text-sky-600 dark:text-sky-400 border border-sky-300 dark:border-sky-800 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition" @click="activeModal = {{ $item['id'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg> Detail
                            </button>
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
                         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
                         x-cloak>
                        
                        {{-- Backdrop --}}
                        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="activeModal = null"></div>

                        {{-- Modal Box --}}
                        <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-6 z-10 max-h-[90vh] overflow-y-auto" 
                             @click.stop>
                            <div class="flex items-start justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-5">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 dark:border-slate-700 p-1.5 flex items-center justify-center flex-shrink-0 shadow-xs">
                                        <img src="{{ $this->getLogo($item) }}" alt="{{ $item['singkatan'] ?? 'Logo' }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                    </div>
                                    <div>
                                        <h5 class="font-bold text-lg text-slate-900 dark:text-slate-100 mb-1 font-display">
                                            {{ $item['nama'] }}
                                        </h5>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
                                                {{ $item['label_tingkat'] }}
                                            </span>
                                            @if (!empty($item['akreditasi']))
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> Terakreditasi {{ $item['akreditasi'] }}
                                                </span>
                                            @endif
                                            @if (!empty($item['npsn']))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                                    NPSN: {{ $item['npsn'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition" @click="activeModal = null">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60">
                                        <h6 class="font-bold text-xs uppercase tracking-wider text-sky-600 dark:text-sky-400 mb-3 flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg>
                                            Informasi Umum
                                        </h6>
                                        <div class="space-y-2 text-xs">
                                            @if (!empty($item['npsn']))
                                                <div>
                                                    <span class="text-slate-500 dark:text-slate-400 block text-[11px]">NPSN:</span>
                                                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ $item['npsn'] }}</span>
                                                </div>
                                            @endif
                                            @if (!empty($item['nomor_statistik']))
                                                <div>
                                                    <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Nomor Statistik:</span>
                                                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ $item['nomor_statistik'] }}</span>
                                                </div>
                                            @endif
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Kepala Lembaga:</span>
                                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $item['kepala'] ?: '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Alamat Lengkap:</span>
                                                <span class="text-slate-700 dark:text-slate-300 leading-snug">{{ $item['alamat'] ?: '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Kontak / Telepon:</span>
                                                <span class="text-slate-700 dark:text-slate-300">{{ $item['telp'] ?: $item['kontak'] ?: '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Email Resmi:</span>
                                                <span class="text-slate-700 dark:text-slate-300">{{ $item['email'] ?: '-' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/60">
                                        <h6 class="font-bold text-xs uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-3 flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                            Jadwal Operasional
                                        </h6>
                                        <div class="text-xs mb-4">
                                            @if (is_array($item['jam_kerja'] ?? null))
                                                <ul class="space-y-1 text-slate-700 dark:text-slate-300">
                                                    @foreach ($item['jam_kerja'] as $jam)
                                                        <li class="flex items-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" /></svg>
                                                            <span>{{ $jam }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="text-slate-700 dark:text-slate-300">{{ $item['jam_kerja'] ?? 'Senin - Sabtu' }}</div>
                                            @endif
                                        </div>

                                        <h6 class="font-bold text-xs uppercase tracking-wider text-cyan-600 dark:text-cyan-400 mb-2 flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                            Sivitas Akademika
                                        </h6>
                                        <div class="flex items-center gap-4 text-xs">
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Siswa:</span>
                                                <span class="text-base font-bold text-sky-600 dark:text-sky-400">{{ $item['total_siswa'] ?? 0 }}</span>
                                            </div>
                                            <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>
                                            <div>
                                                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Guru & Staf:</span>
                                                <span class="text-base font-bold text-amber-500 dark:text-amber-400">{{ $item['total_pegawai'] ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Google Maps Embed if available --}}
                                @if (!empty($item['maps_embed']))
                                    <div class="rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 max-h-56">
                                        {!! $item['maps_embed'] !!}
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-5 border-t border-slate-200 dark:border-slate-800 mt-6">
                                @if (!empty($item['website']))
                                    <a href="{{ str_starts_with($item['website'], 'http') ? $item['website'] : 'https://' . $item['website'] }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-xl text-xs font-semibold border border-sky-300 dark:border-sky-800 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition">
                                        Kunjungi Website
                                    </a>
                                @endif
                                @if ($unitWa)
                                    <a href="{{ $unitWa }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition">
                                        Hubungi WhatsApp
                                    </a>
                                @endif
                                <button type="button" class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition" @click="activeModal = null">
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-10 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                        <p class="text-sm">Belum ada data satuan pendidikan.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- SECTION: NILAI & KEUNGGULAN LEMBAGA --}}
    <section class="py-12" id="keunggulan">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 mb-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" /></svg> Nilai & Karakteristik
                </span>
                <h2 class="text-2xl md:text-4xl font-extrabold font-display text-slate-900 dark:text-slate-100 mb-2">
                    Komitmen Keunggulan Pendidikan Terpadu
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Menghubungkan nilai-nilai akhlak karimah dengan penguasaan ilmu pengetahuan dan transformasi teknologi pendidikan modern.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-3xl glass-card flex flex-col justify-start">
                    <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>
                    </div>
                    <h5 class="font-bold text-base text-slate-900 dark:text-slate-100 mb-2 font-display">Pendidikan Berkelanjutan</h5>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-0">
                        Jenjang pendidikan terstruktur mulai dari tingkat dasar hingga tingkat atas dalam satu ekosistem pendidikan terpadu.
                    </p>
                </div>

                <div class="p-6 rounded-3xl glass-card flex flex-col justify-start">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M11.25 4.533A9.707 9.707 0 0 0 6 3a9.735 9.735 0 0 0-3.25.555.75.75 0 0 0-.5.707v14.25a.75.75 0 0 0 1 .707A8.237 8.237 0 0 1 6 18.75c1.995 0 3.823.707 5.25 1.886V4.533ZM12.75 20.636A8.214 8.214 0 0 1 18 18.75c.966 0 1.89.166 2.75.47a.75.75 0 0 0 1-.708V4.262a.75.75 0 0 0-.5-.707A9.735 9.735 0 0 0 18 3a9.707 9.707 0 0 0-5.25 1.533v16.103Z" /></svg>
                    </div>
                    <h5 class="font-bold text-base text-slate-900 dark:text-slate-100 mb-2 font-display">Nilai Keislaman & Moral</h5>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-0">
                        Menanamkan pembiasaan ibadah harian, adab sopan santun, hafalan Al-Qur'an, dan wawasan keagamaan Islam yang moderat.
                    </p>
                </div>

                <div class="p-6 rounded-3xl glass-card flex flex-col justify-start">
                    <div class="w-12 h-12 rounded-2xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 5.25a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3V15a3 3 0 0 1-3 3h-3v.257c0 .597.237 1.17.659 1.591l.621.622a.75.75 0 0 1-.53 1.28h-9a.75.75 0 0 1-.53-1.28l.621-.622a2.25 2.25 0 0 0 .659-1.59V18h-3a3 3 0 0 1-3-3V5.25Zm1.5 0v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5Z" clip-rule="evenodd" /></svg>
                    </div>
                    <h5 class="font-bold text-base text-slate-900 dark:text-slate-100 mb-2 font-display">Digitalisasi SIMBA</h5>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-0">
                        Didukung sistem informasi manajemen sekolah yang canggih untuk absensi kehadiran, rekapitulasi nilai, dan jurnal belajar mengajar.
                    </p>
                </div>

                <div class="p-6 rounded-3xl glass-card flex flex-col justify-start">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor"><path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" /></svg>
                    </div>
                    <h5 class="font-bold text-base text-slate-900 dark:text-slate-100 mb-2 font-display">Pendidik Berdedikasi</h5>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-0">
                        Didampingi tenaga guru yang kompeten, berakhlak mulia, dan berorientasi pada perkembangan potensi setiap anak didik.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION: PETA LOKASI TERPADU --}}
    <section class="py-12" id="peta-lokasi">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="p-6 md:p-8 rounded-3xl glass-card">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 mb-2.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-rose-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg> Lokasi Kampus Terpadu
                        </span>
                        <h3 class="font-bold text-2xl text-slate-900 dark:text-slate-100 mb-3 font-display">
                            Kawasan Pendidikan {{ $lembagaUtama['nama'] ?? 'Yayasan' }}
                        </h3>
                        <p class="text-xs md:text-sm text-slate-600 dark:text-slate-400 mb-5 leading-relaxed">
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

                        <div class="space-y-3 mb-6">
                            @if (!empty($alamatKampus))
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-rose-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                    <div>
                                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">Alamat Kampus:</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $alamatKampus }}</div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($kontakKampus))
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                    <div>
                                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">Telepon / WhatsApp:</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $kontakKampus }}</div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($emailKampus))
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z" /><path d="M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z" /></svg>
                                    <div>
                                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">Email:</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $emailKampus }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @php
                            $mapsTarget = gmaps_url(99) ?: (gmaps_url(1) ?: (!empty($alamatKampus) ? 'https://maps.google.com/?q=' . urlencode($alamatKampus) : '#'));
                        @endphp
                        <a href="{{ $mapsTarget }}" target="_blank" rel="noopener" class="btn-primary-custom">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                            <span>Buka di Google Maps</span>
                        </a>
                    </div>

                    <div class="lg:col-span-7">
                        <div class="rounded-3xl overflow-hidden shadow-sm h-80 md:h-96 border border-slate-200 dark:border-slate-800">
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
    <section class="py-12">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="p-8 md:p-12 rounded-3xl text-center text-white relative overflow-hidden bg-gradient-to-r from-sky-600 via-indigo-600 to-blue-700 shadow-2xl">
                <div class="relative z-10 max-w-2xl mx-auto">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-xs mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" /></svg> Sistem Informasi SIMBA
                    </span>
                    <h2 class="text-2xl md:text-4xl font-extrabold mb-3 font-display">
                        Portal Akademik & Presensi Pembelajaran
                    </h2>
                    <p class="text-sm md:text-base text-white/80 mb-6 leading-relaxed">
                        Kemudahan pengelolaan presensi real-time, pencatatan jurnal mengajar, input nilai, serta data kepegawaian dan kesiswaan bagi seluruh civitas lembaga.
                    </p>
                    <div class="flex justify-center gap-3 flex-wrap">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 bg-white text-slate-900 hover:bg-slate-100 font-bold px-6 py-3 rounded-full shadow-lg transition text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                Buka Dashboard Saya
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-white text-slate-900 hover:bg-slate-100 font-bold px-6 py-3 rounded-full shadow-lg transition text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                                Masuk Akun SIMBA
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
