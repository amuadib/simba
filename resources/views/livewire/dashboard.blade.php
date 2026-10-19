<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Pegawai;
use App\Models\SkPenugasan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public $totalSiswa = 0;
    public $totalPegawai = 0;
    public $total = ['a' => 0, 'h' => 0, 's' => 0, 'i' => 0];
    public $chartData = ['labels' => [], 'series' => ['H' => [], 'S' => [], 'I' => [], 'A' => []]];
    public $filterKehadiran = '30_hari';
    public $jadwalHariIni = [];
    public $lembaga = [];
    public $siswaSummary = [];
    public $pegawaiSummary = [];

    public function mount()
    {
        $this->totalSiswa = Siswa::count();
        $this->totalPegawai = Pegawai::count();

        $lembagaList = \App\Models\Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];
        if (isset($lembagaList[99])) {
            $this->lembaga = $lembagaList[99];
        } elseif (!empty($lembagaList)) {
            ksort($lembagaList, SORT_NUMERIC);
            $this->lembaga = reset($lembagaList);
        } else {
            $this->lembaga = [];
        }

        // Jadwal hari ini untuk user yang login
        $hariIni = (int) Carbon::today()->isoWeekday(); // 1=Senin ... 7=Ahad
        $hariCarbon = $hariIni;

        $this->jadwalHariIni = Jadwal::with(['pembelajaran.pelajaran', 'pembelajaran.tahunAjaran', 'pembelajaran.anggota', 'pembelajaran.latestJurnal'])
            ->whereHas('pembelajaran.tahunAjaran', fn($q) => $q->where('id', session('tahun_ajaran_id')))
            ->where('user_id', auth()->id())
            ->where('hari', $hariCarbon)
            ->orderBy('jam_mulai')
            ->get()
            ->toArray();

        $this->total = Cache::remember('total_presensi', now()->addMinutes(10), function () {
            $h = $s = $i = $a = 0;
            foreach (Presensi::where('tanggal', date('Y-m-d'))->get() as $p) {
                if ($p->status == 'A') {
                    $a++;
                }
                if ($p->status == 'H') {
                    $h++;
                }
                if ($p->status == 'I') {
                    $i++;
                }
                if ($p->status == 'S') {
                    $s++;
                }
            }

            return ['a' => $a, 'h' => $h, 's' => $s, 'i' => $i];
        });

        $this->loadChartData();
        $this->loadSummaryData();
    }

    public function updatedFilterKehadiran()
    {
        $this->loadChartData();
        $this->dispatch('update-chart');
    }

    public function loadChartData()
    {
        $this->chartData = Cache::remember('chart_presensi_' . $this->filterKehadiran . '_' . date('Ymd'), now()->addMinutes(10), function () {
            if ($this->filterKehadiran == '7_hari') {
                $dates = collect(range(6, 0))->map(fn($i) => Carbon::today()->subDays($i));
            } elseif ($this->filterKehadiran == 'bulan_ini') {
                $dates = collect(range(1, Carbon::today()->day))->map(fn($i) => Carbon::today()->setDay($i));
            } else {
                $dates = collect(range(29, 0))->map(fn($i) => Carbon::today()->subDays($i));
            }

            $chart = ['labels' => [], 'series' => ['H' => [], 'S' => [], 'I' => [], 'A' => []]];

            foreach ($dates as $d) {
                $formattedDate = $d->format('Y-m-d');
                $chart['labels'][] = $d->format('d M');
                $row = Presensi::whereDate('tanggal', $d)
                    ->selectRaw(
                        "
                        SUM(status = 'H') as H,
                        SUM(status = 'I') as I,
                        SUM(status = 'S') as S,
                        SUM(status = 'A') as A
                    "
                    )
                    ->first();
                $chart['series']['H'][] = (int) ($row->H ?? 0);
                $chart['series']['I'][] = (int) ($row->I ?? 0);
                $chart['series']['S'][] = (int) ($row->S ?? 0);
                $chart['series']['A'][] = (int) ($row->A ?? 0);
            }

            return $chart;
        });
    }

    public function loadSummaryData()
    {
        // 1. Ringkasan Siswa
        $this->siswaSummary = Cache::remember('dashboard_siswa_summary', now()->addMinutes(10), function () {
            $total = Siswa::count();
            $aktif = Siswa::where('status', 1)->count();
            $genderL = Siswa::whereRaw("LOWER(jenis_kelamin) = 'l'")->count();
            $genderP = Siswa::whereRaw("LOWER(jenis_kelamin) = 'p'")->count();
            $genderOther = max(0, $total - ($genderL + $genderP));

            $rawLembaga = Siswa::selectRaw("
                COALESCE(lembaga_id, 99) as lembaga_id,
                SUM(CASE WHEN LOWER(jenis_kelamin) = 'l' THEN 1 ELSE 0 END) as l,
                SUM(CASE WHEN LOWER(jenis_kelamin) = 'p' THEN 1 ELSE 0 END) as p,
                COUNT(*) as total
            ")
            ->groupBy(DB::raw("COALESCE(lembaga_id, 99)"))
            ->orderByDesc('total')
            ->get();

            $shortLabels = [];
            $fullLabels = [];
            $seriesL = [];
            $seriesP = [];
            $seriesTotal = [];

            foreach ($rawLembaga as $row) {
                $lid = $row->lembaga_id;
                $shortLabels[] = nama_lembaga($lid, true);
                $fullLabels[] = nama_lembaga($lid);
                $seriesL[] = (int) $row->l;
                $seriesP[] = (int) $row->p;
                $seriesTotal[] = (int) $row->total;
            }

            $statusConfig = config('local.status_siswa', [
                1 => 'Aktif',
                2 => 'Lulus',
                3 => 'Mutasi',
                4 => 'Non-Aktif',
                5 => 'Almarhum',
                6 => 'Keluar',
            ]);

            $rawStatus = Siswa::selectRaw("status, count(*) as total")
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $statusLabels = [];
            $statusData = [];
            foreach ($rawStatus as $st) {
                $name = $statusConfig[$st->status] ?? ('Status ' . $st->status);
                $statusLabels[] = $name;
                $statusData[] = (int) $st->total;
            }

            return [
                'total' => $total,
                'aktif' => $aktif,
                'gender' => [
                    'L' => $genderL,
                    'P' => $genderP,
                    'other' => $genderOther,
                    'pct_L' => $total > 0 ? round(($genderL / $total) * 100, 1) : 0,
                    'pct_P' => $total > 0 ? round(($genderP / $total) * 100, 1) : 0,
                ],
                'by_lembaga' => [
                    'short_labels' => $shortLabels,
                    'labels' => $fullLabels,
                    'series_l' => $seriesL,
                    'series_p' => $seriesP,
                    'total' => $seriesTotal,
                ],
                'by_status' => [
                    'labels' => $statusLabels,
                    'data' => $statusData,
                ],
            ];
        });

        // 2. Ringkasan Pegawai
        $this->pegawaiSummary = Cache::remember('dashboard_pegawai_summary', now()->addMinutes(10), function () {
            $total = Pegawai::count();
            $aktif = Pegawai::where('status', '1')->count();
            $genderL = Pegawai::whereRaw("LOWER(jenis_kelamin) = 'l'")->count();
            $genderP = Pegawai::whereRaw("LOWER(jenis_kelamin) = 'p'")->count();
            $genderOther = max(0, $total - ($genderL + $genderP));

            $rawLembaga = Pegawai::selectRaw("
                COALESCE(lembaga_id, 99) as lembaga_id,
                SUM(CASE WHEN LOWER(jenis_kelamin) = 'l' THEN 1 ELSE 0 END) as l,
                SUM(CASE WHEN LOWER(jenis_kelamin) = 'p' THEN 1 ELSE 0 END) as p,
                COUNT(*) as total
            ")
            ->groupBy(DB::raw("COALESCE(lembaga_id, 99)"))
            ->orderByDesc('total')
            ->get();

            $shortLabels = [];
            $fullLabels = [];
            $seriesL = [];
            $seriesP = [];
            $seriesTotal = [];

            foreach ($rawLembaga as $row) {
                $lid = $row->lembaga_id;
                $shortLabels[] = nama_lembaga($lid, true);
                $fullLabels[] = nama_lembaga($lid);
                $seriesL[] = (int) $row->l;
                $seriesP[] = (int) $row->p;
                $seriesTotal[] = (int) $row->total;
            }

            $jabatanConfig = daftar_jabatan();
            $rawJabatan = SkPenugasan::where('status', '1')
                ->whereNotNull('jabatan')
                ->selectRaw("jabatan, count(*) as total")
                ->groupBy('jabatan')
                ->orderByDesc('total')
                ->get();

            $jabatanLabels = [];
            $jabatanData = [];
            $guruCount = 0;
            $stafCount = 0;

            foreach ($rawJabatan as $jb) {
                $name = $jabatanConfig[$jb->jabatan] ?? ('Jabatan ' . $jb->jabatan);
                $jabatanLabels[] = $name;
                $jabatanData[] = (int) $jb->total;

                if (in_array((int) $jb->jabatan, [3, 4, 6])) {
                    $guruCount += (int) $jb->total;
                } else {
                    $stafCount += (int) $jb->total;
                }
            }

            $statusPegawaiConfig = config('local.status_pegawai', [
                1 => 'Aktif',
                2 => 'Cuti',
                3 => 'Non-Aktif',
                4 => 'Mutasi',
                5 => 'Pensiun',
                6 => 'Keluar',
                7 => 'Almarhum',
            ]);

            $rawStatus = Pegawai::selectRaw("status, count(*) as total")
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $statusLabels = [];
            $statusData = [];
            foreach ($rawStatus as $st) {
                $name = $statusPegawaiConfig[(int) $st->status] ?? ('Status ' . $st->status);
                $statusLabels[] = $name;
                $statusData[] = (int) $st->total;
            }

            return [
                'total' => $total,
                'aktif' => $aktif,
                'guru_count' => $guruCount,
                'staf_count' => $stafCount,
                'gender' => [
                    'l' => $genderL,
                    'p' => $genderP,
                    'other' => $genderOther,
                    'pct_l' => $total > 0 ? round(($genderL / $total) * 100, 1) : 0,
                    'pct_p' => $total > 0 ? round(($genderP / $total) * 100, 1) : 0,
                ],
                'by_lembaga' => [
                    'short_labels' => $shortLabels,
                    'labels' => $fullLabels,
                    'series_l' => $seriesL,
                    'series_p' => $seriesP,
                    'total' => $seriesTotal,
                ],
                'by_jabatan' => [
                    'labels' => $jabatanLabels,
                    'data' => $jabatanData,
                ],
                'by_status' => [
                    'labels' => $statusLabels,
                    'data' => $statusData,
                ],
            ];
        });
    }
};

?>

<div x-data="{
    initPresensiChart() {
        const ctx = document.getElementById('presensiChart');
        if (!ctx) return;
        if (typeof Chart === 'undefined') {
            setTimeout(() => this.initPresensiChart(), 60);
            return;
        }

        if (window.presensiChart && typeof window.presensiChart.destroy === 'function') {
            window.presensiChart.destroy();
        }

        // Deep clone data to strip Livewire proxies and avoid Chart.js infinite loop
        const dataSet = JSON.parse(JSON.stringify($wire.chartData));

        window.presensiChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dataSet.labels,
                datasets: [
                    { label: 'Hadir', data: dataSet.series.H, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Izin', data: dataSet.series.I, borderColor: '#00b4d8', backgroundColor: 'rgba(0,180,216,.12)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Sakit', data: dataSet.series.S, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.12)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Alpa', data: dataSet.series.A, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.12)', fill: true, tension: .3, pointRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });

        const container = document.querySelector('.chart-container');
        if (container) container.scrollLeft = container.scrollWidth;
    }
}" x-init="initPresensiChart()" @update-chart.window="
    if(window.presensiChart) {
        const newData = JSON.parse(JSON.stringify($wire.chartData));
        window.presensiChart.data.labels = newData.labels;
        window.presensiChart.data.datasets[0].data = newData.series.H;
        window.presensiChart.data.datasets[1].data = newData.series.I;
        window.presensiChart.data.datasets[2].data = newData.series.S;
        window.presensiChart.data.datasets[3].data = newData.series.A;
        window.presensiChart.update();
    }
">
    {{-- IDENTITY BANNER --}}
    <div class="relative overflow-hidden rounded-3xl p-4 md:p-6 mb-6 glass-panel border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="absolute top-0 left-0 w-2 h-full bg-gradient-to-b from-[#0284c7] via-[#00b4d8] to-[#10b981]"></div>
        
        <div class="flex items-center flex-wrap gap-4 pl-2">
            <div class="relative flex-shrink-0 w-20 h-20 rounded-2xl bg-gradient-to-tr from-sky-500/15 via-cyan-500/15 to-emerald-500/20 p-2 ring-1 ring-cyan-500/30 shadow-md flex items-center justify-center">
                <img src="{{ logo_url() }}" alt="Logo" class="w-full h-full object-contain">
            </div>
            
            <div class="flex-1 min-w-[240px]">
                <div class="text-xs font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 font-display">
                    {{ setting('nama_aplikasi') ?? env('APP_NAME') }}
                </div>
                <div class="flex items-center gap-2 flex-wrap mt-0.5">
                    <h3 class="text-xl md:text-2xl font-extrabold text-slate-800 dark:text-slate-100 font-display m-0">
                        {{ $lembaga['nama'] ?? 'Lembaga' }}
                    </h3>
                    @if (!empty($lembaga['akreditasi']))
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                            <i class="bi bi-patch-check-fill"></i> Akreditasi {{ $lembaga['akreditasi'] }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-3 md:gap-4 flex-wrap mt-2 text-xs text-slate-500 dark:text-slate-400">
                    @if (!empty($lembaga['npsn']))
                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-card-heading text-sky-500"></i>NPSN: {{ $lembaga['npsn'] }}</span>
                    @endif
                    @if (!empty($lembaga['alamat']))
                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-geo-alt-fill text-emerald-500"></i>{{ $lembaga['alamat'] }}</span>
                    @endif
                    @if (!empty($lembaga['email']))
                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-envelope-fill text-cyan-500"></i>{{ $lembaga['email'] }}</span>
                    @endif
                    @if (!empty($lembaga['kontak']))
                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-telephone-fill text-lime-500"></i>{{ $lembaga['kontak'] }}</span>
                    @endif
                    @if (!empty($lembaga['website']))
                        <a href="{{ str_starts_with($lembaga['website'], 'http') ? $lembaga['website'] : 'https://' . $lembaga['website'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-cyan-600 hover:underline">
                            <i class="bi bi-globe"></i>{{ $lembaga['website'] }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 mb-6">
        {{-- Total Siswa --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-sky-500 to-cyan-500 text-white flex items-center justify-center text-xl shadow-md shadow-sky-500/30 flex-shrink-0">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-display">Total Siswa</div>
                    <div class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $totalSiswa }}</div>
                </div>
            </div>
        </div>

        {{-- Total Pegawai --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-indigo-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center text-xl shadow-md shadow-indigo-500/30 flex-shrink-0">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-display">Total Pegawai</div>
                    <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 font-display">{{ $totalPegawai }}</div>
                </div>
            </div>
        </div>

        {{-- Hadir --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-emerald-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-green-500 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-500/30 flex-shrink-0">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-display">Hadir Hari Ini</div>
                    <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-display">{{ $total['h'] }}</div>
                </div>
            </div>
        </div>

        {{-- Izin --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-cyan-500 to-sky-400 text-white flex items-center justify-center text-xl shadow-md shadow-cyan-500/30 flex-shrink-0">
                    <i class="bi bi-card-text"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 font-display">Izin</div>
                    <div class="text-2xl font-extrabold text-cyan-600 dark:text-cyan-400 font-display">{{ $total['i'] }}</div>
                </div>
            </div>
        </div>

        {{-- Sakit --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-amber-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-400 to-yellow-500 text-white flex items-center justify-center text-xl shadow-md shadow-amber-500/30 flex-shrink-0">
                    <i class="bi bi-bandaid-fill"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-amber-500 font-display">Sakit</div>
                    <div class="text-2xl font-extrabold text-amber-500 font-display">{{ $total['s'] }}</div>
                </div>
            </div>
        </div>

        {{-- Alpa --}}
        <div class="glass-panel p-4 rounded-2xl border border-slate-200/90 dark:border-rose-500/20 hover:scale-[1.02] transition-transform shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-rose-500 to-pink-500 text-white flex items-center justify-center text-xl shadow-md shadow-rose-500/30 flex-shrink-0">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-rose-500 font-display">Alpa</div>
                    <div class="text-2xl font-extrabold text-rose-500 font-display">{{ $total['a'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================
         GRAFIK RINGKASAN DATA SISWA & PEGAWAI
         ======================================================== --}}
    <div class="mb-6" x-data="{
        siswaTab: 'lembaga',
        pegawaiTab: 'lembaga',
        siswaData: {{ json_encode($siswaSummary) }},
        pegawaiData: {{ json_encode($pegawaiSummary) }},
        siswaChart: null,
        pegawaiChart: null,

        init() {
            this.$nextTick(() => {
                this.renderSiswaChart();
                this.renderPegawaiChart();
            });

            // Re-render when theme changes (light/dark)
            const observer = new MutationObserver(() => {
                this.renderSiswaChart();
                this.renderPegawaiChart();
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
        },

        setSiswaTab(tab) {
            this.siswaTab = tab;
            this.$nextTick(() => this.renderSiswaChart());
        },

        setPegawaiTab(tab) {
            this.pegawaiTab = tab;
            this.$nextTick(() => this.renderPegawaiChart());
        },

        isDarkMode() {
            return document.documentElement.classList.contains('dark') || document.documentElement.getAttribute('data-theme') === 'dark';
        },

        renderSiswaChart() {
            const canvas = document.getElementById('siswaSummaryChart');
            if (!canvas) return;
            if (typeof Chart === 'undefined') {
                setTimeout(() => this.renderSiswaChart(), 60);
                return;
            }

            if (this.siswaChart && typeof this.siswaChart.destroy === 'function') {
                this.siswaChart.destroy();
                this.siswaChart = null;
            }

            const dark = this.isDarkMode();
            const textColor = dark ? '#94a3b8' : '#64748b';
            const gridColor = dark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.05)';

            let config = {};

            if (this.siswaTab === 'lembaga') {
                config = {
                    type: 'bar',
                    data: {
                        labels: this.siswaData.by_lembaga.short_labels,
                        datasets: [
                            {
                                label: 'Laki-Laki',
                                data: this.siswaData.by_lembaga.series_l,
                                backgroundColor: 'rgba(2, 132, 199, 0.85)',
                                borderColor: '#0284c7',
                                borderWidth: 1,
                                borderRadius: 6,
                            },
                            {
                                label: 'Perempuan',
                                data: this.siswaData.by_lembaga.series_p,
                                backgroundColor: 'rgba(244, 63, 94, 0.85)',
                                borderColor: '#f43f5e',
                                borderWidth: 1,
                                borderRadius: 6,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: textColor, boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                callbacks: {
                                    afterBody: (items) => {
                                        const idx = items[0].dataIndex;
                                        const total = this.siswaData.by_lembaga.total[idx];
                                        const fullName = this.siswaData.by_lembaga.labels[idx];
                                        return `\nTotal: ${total} Siswa\nUnit: ${fullName}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: textColor, font: { weight: '600' } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: textColor, precision: 0 }
                            }
                        }
                    }
                };
            } else if (this.siswaTab === 'gender') {
                config = {
                    type: 'doughnut',
                    data: {
                        labels: ['Laki-Laki', 'Perempuan'],
                        datasets: [{
                            data: [this.siswaData.gender.L, this.siswaData.gender.P],
                            backgroundColor: ['#0284c7', '#f43f5e'],
                            hoverOffset: 6,
                            borderWidth: 2,
                            borderColor: dark ? '#0a1b32' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: textColor, padding: 16, boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const val = ctx.raw;
                                        const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ` ${ctx.label}: ${val} Siswa (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                };
            } else if (this.siswaTab === 'status') {
                config = {
                    type: 'doughnut',
                    data: {
                        labels: this.siswaData.by_status.labels,
                        datasets: [{
                            data: this.siswaData.by_status.data,
                            backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#64748b', '#ef4444', '#ec4899'],
                            hoverOffset: 6,
                            borderWidth: 2,
                            borderColor: dark ? '#0a1b32' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: textColor, padding: 12, boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const val = ctx.raw;
                                        const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ` ${ctx.label}: ${val} Siswa (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                };
            }

            this.siswaChart = new Chart(canvas, config);
        },

        renderPegawaiChart() {
            const canvas = document.getElementById('pegawaiSummaryChart');
            if (!canvas) return;
            if (typeof Chart === 'undefined') {
                setTimeout(() => this.renderPegawaiChart(), 60);
                return;
            }

            if (this.pegawaiChart && typeof this.pegawaiChart.destroy === 'function') {
                this.pegawaiChart.destroy();
                this.pegawaiChart = null;
            }

            const dark = this.isDarkMode();
            const textColor = dark ? '#94a3b8' : '#64748b';
            const gridColor = dark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.05)';

            let config = {};

            if (this.pegawaiTab === 'lembaga') {
                config = {
                    type: 'bar',
                    data: {
                        labels: this.pegawaiData.by_lembaga.short_labels,
                        datasets: [
                            {
                                label: 'Laki-Laki',
                                data: this.pegawaiData.by_lembaga.series_l,
                                backgroundColor: 'rgba(99, 102, 241, 0.85)',
                                borderColor: '#6366f1',
                                borderWidth: 1,
                                borderRadius: 6,
                            },
                            {
                                label: 'Perempuan',
                                data: this.pegawaiData.by_lembaga.series_p,
                                backgroundColor: 'rgba(236, 72, 153, 0.85)',
                                borderColor: '#ec4899',
                                borderWidth: 1,
                                borderRadius: 6,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: textColor, boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                callbacks: {
                                    afterBody: (items) => {
                                        const idx = items[0].dataIndex;
                                        const total = this.pegawaiData.by_lembaga.total[idx];
                                        const fullName = this.pegawaiData.by_lembaga.labels[idx];
                                        return `\nTotal: ${total} Pegawai\nUnit: ${fullName}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: textColor, font: { weight: '600' } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: textColor, precision: 0 }
                            }
                        }
                    }
                };
            } else if (this.pegawaiTab === 'jabatan') {
                config = {
                    type: 'bar',
                    data: {
                        labels: this.pegawaiData.by_jabatan.labels,
                        datasets: [{
                            label: 'Jumlah Pegawai',
                            data: this.pegawaiData.by_jabatan.data,
                            backgroundColor: 'rgba(139, 92, 246, 0.85)',
                            borderColor: '#8b5cf6',
                            borderWidth: 1,
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ` ${ctx.raw} Pegawai`
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: textColor, precision: 0 }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { color: textColor, font: { size: 11, weight: '500' } }
                            }
                        }
                    }
                };
            } else if (this.pegawaiTab === 'status') {
                config = {
                    type: 'doughnut',
                    data: {
                        labels: this.pegawaiData.by_status.labels,
                        datasets: [{
                            data: this.pegawaiData.by_status.data,
                            backgroundColor: ['#10b981', '#f59e0b', '#64748b', '#ef4444', '#6366f1'],
                            hoverOffset: 6,
                            borderWidth: 2,
                            borderColor: dark ? '#0a1b32' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: textColor, padding: 12, boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const val = ctx.raw;
                                        const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ` ${ctx.label}: ${val} Pegawai (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                };
            }

            this.pegawaiChart = new Chart(canvas, config);
        }
    }">
        {{-- Section Header --}}
        <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
            <div>
                <h5 class="text-lg font-extrabold text-slate-800 dark:text-slate-100 font-display m-0 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 text-white flex items-center justify-center text-sm shadow-md shadow-cyan-500/20">
                        <i class="bi bi-bar-chart-fill"></i>
                    </span>
                    Ringkasan Data Siswa & Pegawai
                </h5>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 mb-0">
                    Statistik persebaran data siswa dan pendidik/tenaga kependidikan
                </p>
            </div>
            
            {{-- Quick Indicator pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-500/20">
                    <i class="bi bi-people-fill text-sky-500"></i> {{ $siswaSummary['total'] ?? 0 }} Siswa ({{ $siswaSummary['aktif'] ?? 0 }} Aktif)
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20">
                    <i class="bi bi-person-badge-fill text-indigo-500"></i> {{ $pegawaiSummary['total'] ?? 0 }} Pegawai ({{ $pegawaiSummary['aktif'] ?? 0 }} Aktif)
                </span>
            </div>
        </div>

        {{-- Cards Grid --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            
            {{-- KARTU 1: GRAFIK SISWA --}}
            <div class="glass-panel p-4 md:p-5 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm flex flex-col justify-between">
                <div>
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between flex-wrap gap-2 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-cyan-500 text-white flex items-center justify-center text-lg shadow-md shadow-sky-500/25 flex-shrink-0">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <div>
                                <h6 class="font-extrabold text-slate-800 dark:text-slate-100 font-display m-0">Ringkasan Data Siswa</h6>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Total {{ $siswaSummary['total'] ?? 0 }} siswa terdaftar</span>
                            </div>
                        </div>

                        {{-- Switcher Tab Buttons --}}
                        <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl border border-slate-200/60 dark:border-slate-700/60 text-xs font-semibold">
                            <button type="button" @click="setSiswaTab('lembaga')"
                                :class="siswaTab === 'lembaga' ? 'bg-white dark:bg-slate-700 text-cyan-600 dark:text-cyan-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-buildings"></i> Unit Lembaga
                            </button>
                            <button type="button" @click="setSiswaTab('gender')"
                                :class="siswaTab === 'gender' ? 'bg-white dark:bg-slate-700 text-cyan-600 dark:text-cyan-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-gender-ambiguous"></i> Gender
                            </button>
                            <button type="button" @click="setSiswaTab('status')"
                                :class="siswaTab === 'status' ? 'bg-white dark:bg-slate-700 text-cyan-600 dark:text-cyan-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-pie-chart"></i> Status
                            </button>
                        </div>
                    </div>

                    {{-- Chart Canvas Container --}}
                    <div class="relative w-full h-[270px] my-2" wire:ignore>
                        <canvas id="siswaSummaryChart"></canvas>
                    </div>
                </div>

                {{-- Bottom Stats Pill Strip --}}
                <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80 grid grid-cols-3 gap-2 text-center">
                    <div class="p-2 rounded-xl bg-sky-500/5 dark:bg-sky-500/10 border border-sky-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">Laki-Laki</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $siswaSummary['gender']['L'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">({{ $siswaSummary['gender']['pct_L'] ?? 0 }}%)</span>
                        </div>
                    </div>
                    <div class="p-2 rounded-xl bg-rose-500/5 dark:bg-rose-500/10 border border-rose-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Perempuan</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $siswaSummary['gender']['P'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">({{ $siswaSummary['gender']['pct_P'] ?? 0 }}%)</span>
                        </div>
                    </div>
                    <div class="p-2 rounded-xl bg-emerald-500/5 dark:bg-emerald-500/10 border border-emerald-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Status Aktif</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $siswaSummary['aktif'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">({{ !empty($siswaSummary['total']) ? round(($siswaSummary['aktif'] / $siswaSummary['total']) * 100, 1) : 0 }}%)</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KARTU 2: GRAFIK PEGAWAI --}}
            <div class="glass-panel p-4 md:p-5 rounded-2xl border border-slate-200/90 dark:border-indigo-500/20 shadow-sm flex flex-col justify-between">
                <div>
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between flex-wrap gap-2 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-500/25 flex-shrink-0">
                                <i class="bi bi-person-badge-fill"></i>
                            </div>
                            <div>
                                <h6 class="font-extrabold text-slate-800 dark:text-slate-100 font-display m-0">Ringkasan Data Pegawai</h6>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Total {{ $pegawaiSummary['total'] ?? 0 }} pegawai & pendidik</span>
                            </div>
                        </div>

                        {{-- Switcher Tab Buttons --}}
                        <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl border border-slate-200/60 dark:border-slate-700/60 text-xs font-semibold">
                            <button type="button" @click="setPegawaiTab('lembaga')"
                                :class="pegawaiTab === 'lembaga' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-buildings"></i> Unit Lembaga
                            </button>
                            <button type="button" @click="setPegawaiTab('jabatan')"
                                :class="pegawaiTab === 'jabatan' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-briefcase"></i> Jabatan
                            </button>
                            <button type="button" @click="setPegawaiTab('status')"
                                :class="pegawaiTab === 'status' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                                class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                <i class="bi bi-shield-check"></i> Status
                            </button>
                        </div>
                    </div>

                    {{-- Chart Canvas Container --}}
                    <div class="relative w-full h-[270px] my-2" wire:ignore>
                        <canvas id="pegawaiSummaryChart"></canvas>
                    </div>
                </div>

                {{-- Bottom Stats Pill Strip --}}
                <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80 grid grid-cols-3 gap-2 text-center">
                    <div class="p-2 rounded-xl bg-purple-500/5 dark:bg-purple-500/10 border border-purple-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Guru / Pendidik</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $pegawaiSummary['guru_count'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">Orang</span>
                        </div>
                    </div>
                    <div class="p-2 rounded-xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Tenaga Kependidikan</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $pegawaiSummary['staf_count'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">Orang</span>
                        </div>
                    </div>
                    <div class="p-2 rounded-xl bg-emerald-500/5 dark:bg-emerald-500/10 border border-emerald-500/15">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Pegawai Aktif</div>
                        <div class="text-sm md:text-base font-extrabold text-slate-800 dark:text-slate-100 font-display">
                            {{ $pegawaiSummary['aktif'] ?? 0 }} <span class="text-[11px] font-normal text-slate-500">({{ !empty($pegawaiSummary['total']) ? round(($pegawaiSummary['aktif'] / $pegawaiSummary['total']) * 100, 1) : 0 }}%)</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- JADWAL HARI INI --}}
    @php
        $namaHari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad'];
        $hariIniNum = (int) \Carbon\Carbon::today()->isoWeekday();
        $hariIniText = $namaHari[$hariIniNum] ?? '-';
        $tanggalIni = \Carbon\Carbon::today()->translatedFormat('d F Y');
    @endphp
    <div class="card p-md-4 p-3 shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h6 class="fw-semibold mb-0"><i class="bi bi-calendar2-check me-2" style="color:#6366f1;"></i>Jadwal Hari Ini</h6>
                <small class="text-muted">{{ $hariIniText }}, {{ $tanggalIni }}</small>
            </div>
            <span class="badge rounded-pill" style="background:rgba(99,102,241,.15);color:#6366f1;font-size:.75rem;padding:.35em .8em;">
                {{ count($jadwalHariIni) }} Sesi
            </span>
        </div>

        @if(count($jadwalHariIni) === 0)
            <div class="text-center py-4">
                <div style="font-size:2.5rem;opacity:.25;"><i class="bi bi-calendar-x"></i></div>
                <div class="text-muted mt-2" style="font-size:.875rem;">Tidak ada jadwal mengajar hari ini</div>
            </div>
        @else
            <div class="jadwal-timeline">
                @foreach($jadwalHariIni as $idx => $j)
                    @php
                        $jamMulai    = \Carbon\Carbon::parse($j['jam_mulai'])->format('H:i');
                        $jamSelesai  = \Carbon\Carbon::parse($j['jam_selesai'])->format('H:i');
                        $mapel       = $j['pembelajaran']['pelajaran']['nama'] ?? '-';
                        $keterangan  = $j['pembelajaran']['keterangan'] ?? '';
                        $jumlahSiswa = count($j['pembelajaran']['anggota'] ?? []);
                        $latestJurnal = $j['pembelajaran']['latest_jurnal'] ?? null;
                        $materiTerakhir = $latestJurnal['materi'] ?? null;
                        $tanggalJurnal  = $latestJurnal ? \Carbon\Carbon::parse($latestJurnal['tanggal'])->locale('id_ID')->isoFormat('DD MMM YYYY') : null;
                        $colors = ['#6366f1','#22c55e','#f59e0b','#38bdf8','#ef4444','#a855f7','#14b8a6'];
                        $color  = $colors[$idx % count($colors)];
                    @endphp
                    <div class="jadwal-item d-flex gap-3 align-items-stretch">
                        {{-- time column --}}
                        <div class="jadwal-time text-center" style="min-width:60px;">
                            <div class="fw-bold" style="font-size:.85rem;color:{{ $color }};">{{ $jamMulai }}</div>
                            <div class="jadwal-line" style="border-color:{{ $color }};"></div>
                            <div style="font-size:.75rem;color:#94a3b8;">{{ $jamSelesai }}</div>
                        </div>
                        {{-- card --}}
                        <div class="jadwal-card flex-fill rounded-3 p-3 mb-3" style="border-left:3px solid {{ $color }};background:rgba({{ implode(',', sscanf(ltrim($color,'#'), '%02x%02x%02x')) }},.06);">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                <div class="flex-fill">
                                    <div class="fw-semibold jadwal-mapel" style="font-size:.95rem;">
                                        {{ $mapel }}
                                    </div>
                                    @if($keterangan)
                                        <div class="jadwal-keterangan" style="font-size:.8rem;">
                                            <a wire:navigate href="{{ route('pembelajaran.jurnal.index', ['pembelajaran' => $j['pembelajaran_id']]) }}">
                                                {{ $keterangan }}
                                            </a>
                                        </div>
                                    @endif
                                    {{-- Materi jurnal terakhir --}}
                                    <div class="mt-2 pt-2 jadwal-divider">
                                        @if($materiTerakhir)
                                            <div class="jadwal-label" style="font-size:.78rem;margin-bottom:2px;">
                                                <i class="bi bi-journal-text me-1"></i>
                                                Materi terakhir
                                                <span style="font-size:.72rem;">({{ $tanggalJurnal }})</span>
                                            </div>
                                            <div class="jadwal-materi" style="font-size:.83rem;line-height:1.45;">{!! nl2br(e($materiTerakhir)) !!}</div>
                                        @else
                                            <div class="jadwal-label" style="font-size:.78rem;font-style:italic;">
                                                <i class="bi bi-journal-x me-1"></i>Belum ada jurnal
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-2 ms-2">
                                    <span class="badge" style="background:rgba({{ implode(',', sscanf(ltrim($color,'#'), '%02x%02x%02x')) }},.15);color:{{ $color }};font-size:.72rem;white-space:nowrap;">
                                        <i class="bi bi-people me-1"></i>{{ $jumlahSiswa }} Siswa
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- CHART KEHADIRAN --}}
    <div class="card p-md-4 p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-semibold mb-0">Grafik Kehadiran</h6>
                <small class="text-muted">Statistik presensi siswa</small>
            </div>
            <select wire:model.live="filterKehadiran" class="form-select form-select-sm w-auto">
                <option value="30_hari">30 Hari</option>
                <option value="7_hari">7 Hari</option>
                <option value="bulan_ini">Bulan Ini</option>
            </select>
        </div>
        <div class="d-flex justify-content-center align-items-center chart-container rounded"
            style="overflow-x: auto; width: 100%;" wire:ignore>
            <canvas id="presensiChart" style="min-width: 1000px; height: 260px;"></canvas>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('chart.js') }}"></script>
    @endpush

    <style>
        /* ---- IDENTITY BANNER ---- */
        .identity-banner {
            border-left: 4px solid #6366f1;
        }

        .identity-logo {
            height: 80px;
            width: 80px;
            object-fit: contain;
            border-radius: 10px;
            background: rgba(255, 255, 255, .05);
            padding: 4px;
        }

        .identity-logo-placeholder {
            height: 80px;
            width: 80px;
            border-radius: 10px;
            background: rgba(99, 102, 241, .15);
            color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .identity-lembaga {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .identity-app {
            font-size: .85rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .identity-contacts {
            font-size: .8rem;
            color: #94a3b8;
        }

        .identity-contacts i {
            font-size: .75rem;
        }

        /* ---- STAT CARDS ---- */
        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-label {
            font-size: .75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }

        tr:hover {
            background-color: rgba(13, 110, 253, 0.01) !important;
        }

        /* ---- JADWAL TIMELINE ---- */
        .jadwal-timeline {
            padding-top: 4px;
        }

        .jadwal-item {
            position: relative;
        }

        .jadwal-time {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding-top: 2px;
        }

        .jadwal-line {
            flex: 1;
            border-left: 2px dashed;
            opacity: .35;
            min-height: 20px;
        }

        .jadwal-card {
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .jadwal-card:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        }

        /* ---- JADWAL TEXT (light mode defaults) ---- */
        .jadwal-mapel    { color: #1e293b; }
        .jadwal-keterangan { color: #475569; }
        .jadwal-keterangan a { color: #0d6efd; text-decoration: none; }
        .jadwal-keterangan a:hover { text-decoration: underline; color: #0a58ca; }
        .jadwal-materi   { color: #334155; }
        .jadwal-label    { color: #94a3b8; }
        .jadwal-divider  { border-top: 1px dashed rgba(0,0,0,.1); }

        /* ---- JADWAL TEXT (dark mode overrides) ---- */
        [data-theme="dark"] .jadwal-mapel    { color: #f1f5f9; }
        [data-theme="dark"] .jadwal-keterangan { color: #cbd5e1; }
        [data-theme="dark"] .jadwal-keterangan a { color: #93c5fd; }
        [data-theme="dark"] .jadwal-keterangan a:hover { color: #bfdbfe; text-decoration: underline; }
        [data-theme="dark"] .jadwal-materi   { color: #e2e8f0; }
        [data-theme="dark"] .jadwal-label    { color: #94a3b8; }
        [data-theme="dark"] .jadwal-divider  { border-top: 1px dashed rgba(255,255,255,.12); }
    </style>
</div>
