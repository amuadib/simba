<?php

use App\Models\Jadwal;
use App\Models\Presensi;
use App\Models\Siswa;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public $totalSiswa = 0;
    public $total = ['a' => 0, 'h' => 0, 's' => 0, 'i' => 0];
    public $chartData = ['labels' => [], 'series' => ['H' => [], 'S' => [], 'I' => [], 'A' => []]];
    public $filterKehadiran = '30_hari';
    public $jadwalHariIni = [];

    public function mount()
    {
        $this->totalSiswa = Siswa::count();

        // Jadwal hari ini untuk user yang login
        $hariIni = (int) Carbon::today()->isoWeekday(); // 1=Senin ... 7=Ahad
        // Model memakai hari 7 = Ahad, tapi Carbon: 7 = Sunday — samakan
        $hariCarbon = $hariIni; // Carbon isoWeekday: 1=Mon ... 7=Sun → sesuai model

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
                $chart['series']['H'][] = (int) $row->H;
                $chart['series']['I'][] = (int) $row->I;
                $chart['series']['S'][] = (int) $row->S;
                $chart['series']['A'][] = (int) $row->A;
            }

            return $chart;
        });
    }
};

?>

<div x-data="{
    initChart() {
        const ctx = document.getElementById('presensiChart');
        if (!ctx) return;

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
                    { label: 'Hadir', data: dataSet.series.H, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.1)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Izin', data: dataSet.series.I, borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,.1)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Sakit', data: dataSet.series.S, borderColor: '#facc15', backgroundColor: 'rgba(250,204,21,.1)', fill: true, tension: .3, pointRadius: 4 },
                    { label: 'Alpa', data: dataSet.series.A, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: .3, pointRadius: 4 }
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
}" x-init="initChart()" @update-chart.window="
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
    <div class="identity-banner card p-md-4 mb-4 p-3 shadow-sm">
        <div class="d-flex align-items-center flex-wrap gap-3">
            <img src="{{ logo_url() }}" alt="Logo" class="identity-logo">
            <div class="flex-fill">
                <div class="identity-app">{{ setting('nama_aplikasi') ?? env('APP_NAME') }}</div>
                <div class="identity-lembaga">{{ setting('nama_lembaga') ?? env('APP_NAME') }}</div>
                <div class="identity-contacts d-flex mt-1 flex-wrap gap-3">
                    @if (setting('alamat'))
                        <span><i class="bi bi-geo-alt-fill me-1"></i>{{ setting('alamat') }}</span>
                    @endif
                    @if (setting('email'))
                        <span><i class="bi bi-envelope-fill me-1"></i>{{ setting('email') }}</span>
                    @endif
                    @if (setting('telp'))
                        <span><i class="bi bi-telephone-fill me-1"></i>{{ setting('telp') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- STATS --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(99,102,241,.15); color:#6366f1;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Siswa</div>
                        <div class="stat-value">{{ $totalSiswa }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(34,197,94,.15); color:#22c55e;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Hadir</div>
                        <div class="stat-value text-success">{{ $total['h'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(250,204,21,.15); color:#facc15;">
                        <i class="bi bi-bandaid-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Sakit</div>
                        <div class="stat-value text-warning">{{ $total['s'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(56,189,248,.15); color:#38bdf8;">
                        <i class="bi bi-card-text"></i>
                    </div>
                    <div>
                        <div class="stat-label">Izin</div>
                        <div class="stat-value" style="color:#38bdf8;">{{ $total['i'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(239,68,68,.15); color:#ef4444;">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Alpa</div>
                        <div class="stat-value text-danger">{{ $total['a'] }}</div>
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

    {{-- CHART --}}
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
