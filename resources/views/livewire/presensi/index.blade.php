<?php

use App\Models\Pembelajaran;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\AnggotaPembelajaran;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public $bulan = '';
    public $pembelajaran_id = '';
    public $search = '';
    public $filterStatus = 'all'; // 'all', 'alpa', 'izin_sakit', 'kurang'
    public $tglList = [];
    public $rekap = [];
    public $dailyTotals = [];
    public $selectedPembelajaran = null;
    public $stats = [
        'total_siswa' => 0,
        'total_h' => 0,
        'total_i' => 0,
        'total_s' => 0,
        'total_a' => 0,
        'persentase_hadir' => 0,
        'total_pertemuan' => 0,
    ];

    public function mount()
    {
        $this->pembelajaran_id = request()->pembelajaran_id ?? '';
        $this->bulan = request()->bulan ?? date('Y-m');

        if (empty($this->pembelajaran_id)) {
            $first = $this->pembelajaranList->first();
            if ($first) {
                $this->pembelajaran_id = $first->id;
            }
        }

        $this->loadRekap();
    }

    public function prevMonth()
    {
        $this->bulan = Carbon::parse($this->bulan . '-01')->subMonth()->format('Y-m');
        $this->loadRekap();
    }

    public function nextMonth()
    {
        $this->bulan = Carbon::parse($this->bulan . '-01')->addMonth()->format('Y-m');
        $this->loadRekap();
    }

    public function setBulanCurrent()
    {
        $this->bulan = date('Y-m');
        $this->loadRekap();
    }

    public function updatedPembelajaranId()
    {
        $this->loadRekap();
    }

    public function updatedBulan()
    {
        $this->loadRekap();
    }

    public function loadRekap()
    {
        $this->rekap = [];
        $this->tglList = [];
        $this->dailyTotals = [];
        $this->stats = [
            'total_siswa' => 0,
            'total_h' => 0,
            'total_i' => 0,
            'total_s' => 0,
            'total_a' => 0,
            'persentase_hadir' => 0,
            'total_pertemuan' => 0,
        ];

        if ($this->pembelajaran_id) {
            $this->selectedPembelajaran = Pembelajaran::with(['pelajaran', 'tahunAjaran', 'jadwal.user'])->find($this->pembelajaran_id);

            $start = "$this->bulan-01";
            $end = date('Y-m-t', strtotime($start));

            $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), (new \DateTime($end))->modify('+1 day'));

            $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Ahd'];

            foreach ($period as $d) {
                $dayNum = (int) $d->format('N');
                $dateStr = $d->format('Y-m-d');
                $this->tglList[] = [
                    'date' => $dateStr,
                    'day' => $d->format('d'),
                    'day_num' => $dayNum,
                    'day_name' => $dayNames[$dayNum] ?? '',
                    'is_today' => $dateStr === date('Y-m-d'),
                    'is_sunday' => $dayNum === 7,
                    'is_friday' => $dayNum === 5,
                ];

                $this->dailyTotals[$dateStr] = ['H' => 0, 'I' => 0, 'S' => 0, 'A' => 0];
            }

            // Get members
            $members = AnggotaPembelajaran::where('pembelajaran_id', $this->pembelajaran_id)
                ->whereHas('siswa', fn($q) => $q->where('status', 1))
                ->with('siswa')
                ->get()
                ->sortBy(fn($m) => $m->siswa->nama ?? '', SORT_NATURAL | SORT_FLAG_CASE);

            // Fetch presensi for this month
            $presensiData = Presensi::where('pembelajaran_id', $this->pembelajaran_id)
                ->where('tanggal', 'like', $this->bulan . '-%')
                ->get();

            $presensiMap = [];
            $datesWithPresensi = [];
            foreach ($presensiData as $p) {
                $presensiMap[$p->siswa_id][$p->tanggal] = $p->status;
                if (in_array($p->status, ['H', 'I', 'S', 'A'])) {
                    $datesWithPresensi[$p->tanggal] = true;
                    if (isset($this->dailyTotals[$p->tanggal])) {
                        $this->dailyTotals[$p->tanggal][$p->status]++;
                    }
                }
            }

            $totalSiswa = 0;
            $allH = 0;
            $allI = 0;
            $allS = 0;
            $allA = 0;

            foreach ($members as $m) {
                $s = $m->siswa;
                if (!$s) continue;

                $id = $s->id;
                $totalSiswa++;
                $h = 0;
                $i = 0;
                $sCount = 0;
                $a = 0;

                $tglStatus = [];
                foreach ($this->tglList as $t) {
                    $st = $presensiMap[$id][$t['date']] ?? '-';
                    $tglStatus[$t['date']] = $st;
                    if ($st === 'H') $h++;
                    elseif ($st === 'I') $i++;
                    elseif ($st === 'S') $sCount++;
                    elseif ($st === 'A') $a++;
                }

                $totalPresensi = $h + $i + $sCount + $a;
                $pctHadir = $totalPresensi > 0 ? round(($h / $totalPresensi) * 100, 1) : 0;

                $this->rekap[$id] = [
                    'id' => $id,
                    'nis' => $s->nis ?? $s->nisn ?? '-',
                    'nama_raw' => $s->nama,
                    'nama_processed' => setNama($s->nama, $s->panggilan, $s->jenis_kelamin),
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'H' => $h,
                    'I' => $i,
                    'S' => $sCount,
                    'A' => $a,
                    'total' => $totalPresensi,
                    'pct' => $pctHadir,
                    'tgl' => $tglStatus,
                ];

                $allH += $h;
                $allI += $i;
                $allS += $sCount;
                $allA += $a;
            }

            $grandTotal = $allH + $allI + $allS + $allA;
            $this->stats = [
                'total_siswa' => $totalSiswa,
                'total_h' => $allH,
                'total_i' => $allI,
                'total_s' => $allS,
                'total_a' => $allA,
                'persentase_hadir' => $grandTotal > 0 ? round(($allH / $grandTotal) * 100, 1) : 0,
                'total_pertemuan' => count($datesWithPresensi),
            ];
        }
    }

    public function updateCell($siswaId, $tanggal, $status)
    {
        if ($status == '-') {
            Presensi::where('siswa_id', $siswaId)->where('pembelajaran_id', $this->pembelajaran_id)->where('tanggal', $tanggal)->delete();
        } else {
            Presensi::upsert(
                [
                    [
                        'id' => (string) Str::uuid(),
                        'siswa_id' => $siswaId,
                        'pembelajaran_id' => $this->pembelajaran_id,
                        'tanggal' => $tanggal,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                ['siswa_id', 'pembelajaran_id', 'tanggal'],
                ['status', 'updated_at'],
            );
        }

        $this->loadRekap();
    }

    public function markAllHadir($tanggal)
    {
        if (!$this->pembelajaran_id || empty($this->rekap)) return;

        $rows = [];
        foreach ($this->rekap as $siswaId => $r) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'siswa_id' => $siswaId,
                'pembelajaran_id' => $this->pembelajaran_id,
                'tanggal' => $tanggal,
                'status' => 'H',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Presensi::upsert($rows, ['siswa_id', 'pembelajaran_id', 'tanggal'], ['status', 'updated_at']);
        $this->loadRekap();
        $this->dispatch('toast', message: 'Semua siswa ditandai Hadir pada tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'), type: 'success');
    }

    public function getPembelajaranListProperty()
    {
        $tahunAjaranId = session('tahun_ajaran_id') ?? TahunAjaran::where('aktif', 'y')->value('id');

        return Pembelajaran::with(['pelajaran', 'tahunAjaran', 'jadwal.user'])
            ->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->orderBy('keterangan')
            ->get();
    }

    public function getFilteredRekapProperty()
    {
        $list = collect($this->rekap);

        // Search filter
        if (!empty($this->search)) {
            $keyword = strtolower(trim($this->search));
            $list = $list->filter(function ($item) use ($keyword) {
                return str_contains(strtolower($item['nama_raw']), $keyword) ||
                       str_contains(strtolower((string)$item['nis']), $keyword);
            });
        }

        // Status filter
        if ($this->filterStatus === 'alpa') {
            $list = $list->filter(fn($item) => $item['A'] > 0);
        } elseif ($this->filterStatus === 'izin_sakit') {
            $list = $list->filter(fn($item) => ($item['I'] + $item['S']) > 0);
        } elseif ($this->filterStatus === 'kurang') {
            $list = $list->filter(fn($item) => $item['pct'] < 100);
        }

        return $list;
    }
};

?>

<div>
    {{-- PRINT HEADER (ONLY VISIBLE WHEN PRINTING) --}}
    <div class="d-none d-print-block mb-4 text-center">
        <h4 class="fw-bold mb-1 uppercase tracking-wider">{{ setting('nama_aplikasi') ?? env('APP_NAME') }}</h4>
        <h5 class="fw-bold mb-2">REKAPITULASI PRESENSI KEHADIRAN SISWA</h5>
        <div class="small text-muted mb-2">
            <strong>Mata Pelajaran / Sesi:</strong> {{ $selectedPembelajaran->keterangan ?? '-' }} |
            <strong>Bulan:</strong> {{ Carbon::parse($bulan . '-01')->locale('id_ID')->isoFormat('MMMM YYYY') }} |
            <strong>Tahun Ajaran:</strong> {{ session('tahun_ajaran_nama') ?? ($selectedPembelajaran->tahunAjaran->nama ?? '-') }}
        </div>
        <hr class="my-2 border-dark">
    </div>

    {{-- SCREEN HEADER --}}
    <div class="d-print-none flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Rekap Presensi Kehadiran</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Monitoring dan rekapitulasi kehadiran siswa bulanan</p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- FILTER CONTROLS PANEL --}}
    <div class="d-print-none glass-panel p-3 md:p-4 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm mb-4">
        <div class="row g-3 align-items-center">
            {{-- Pembelajaran Selector --}}
            <div class="col-12 col-md-5">
                <label class="form-label small font-bold text-slate-700 dark:text-slate-300 mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-journal-bookmark-fill text-sky-500"></i> Pilih Pembelajaran / Kelas
                </label>
                <select wire:model.live="pembelajaran_id" class="form-select form-select-sm shadow-xs font-medium">
                    <option value="">-- Pilih Pembelajaran --</option>
                    @foreach ($this->pembelajaranList as $p)
                        @php
                            $guru = $p->jadwal->first()?->user->name ?? null;
                        @endphp
                        <option value="{{ $p->id }}">
                            {{ $p->keterangan }} {{ $guru ? "($guru)" : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Month Navigator --}}
            <div class="col-12 col-md-4">
                <label class="form-label small font-bold text-slate-700 dark:text-slate-300 mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-calendar-month text-cyan-500"></i> Periode Bulan
                </label>
                <div class="input-group input-group-sm">
                    <button type="button" wire:click="prevMonth" class="btn btn-outline-secondary" title="Bulan Sebelumnya">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="month" wire:model.live="bulan" class="form-control text-center font-bold">
                    <button type="button" wire:click="nextMonth" class="btn btn-outline-secondary" title="Bulan Berikutnya">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button type="button" wire:click="setBulanCurrent" class="btn btn-outline-primary" title="Bulan Ini">
                        Hari Ini
                    </button>
                </div>
            </div>

            {{-- Live Search --}}
            <div class="col-12 col-md-3">
                <label class="form-label small font-bold text-slate-700 dark:text-slate-300 mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-search text-slate-400"></i> Cari Siswa
                </label>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search"
                           class="form-control form-control-sm shadow-xs"
                           placeholder="Ketik nama atau NIS...">
                    @if (!empty($search))
                        <button type="button" wire:click="$set('search', '')"
                                class="btn btn-sm btn-link position-absolute end-0 top-0 text-muted"
                                style="text-decoration:none;">
                            <i class="bi bi-x"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Action Buttons & Filter Chips Bar --}}
        @if ($pembelajaran_id)
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 mt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-sky-50 dark:bg-sky-950/30 text-sky-700 dark:text-cyan-400 border border-sky-200/70 dark:border-cyan-500/30">
                        <i class="bi bi-calendar3"></i> Periode: {{ \Carbon\Carbon::parse($bulan . '-01')->translatedFormat('F Y') }}
                    </span>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('pembelajaran.presensi.create', $pembelajaran_id) }}?tanggal={{ date('Y-m-d') }}"
                       wire:navigate
                       class="btn-logo-gradient inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition-all">
                        <i class="bi bi-calendar-check"></i>
                        <span>Input Presensi Hari Ini</span>
                    </a>

                    @if (count($rekap) > 0)
                        <a href="{{ route('pembelajaran.presensi.export', ['pembelajaran_id' => $pembelajaran_id, 'bulan' => $bulan]) }}"
                           class="btn btn-outline-success btn-sm rounded-xl px-3 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Ekspor Excel</span>
                        </a>

                        <button type="button" onclick="window.print()"
                                class="btn btn-outline-secondary btn-sm rounded-xl px-3 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                            <i class="bi bi-printer"></i>
                            <span>Cetak / PDF</span>
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Filter Chips Bar --}}
        @if (count($rekap) > 0)
            <div class="d-flex align-items-center gap-2 flex-wrap pt-3 mt-3 border-top border-slate-200/60 dark:border-slate-700/60">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Filter Tampilan:</span>
                
                <button type="button" wire:click="$set('filterStatus', 'all')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border
                               {{ $filterStatus === 'all' 
                                   ? 'bg-sky-500 text-white border-sky-500 shadow-sm' 
                                   : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
                    Semua Siswa ({{ count($rekap) }})
                </button>

                <button type="button" wire:click="$set('filterStatus', 'alpa')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border
                               {{ $filterStatus === 'alpa' 
                                   ? 'bg-rose-500 text-white border-rose-500 shadow-sm' 
                                   : 'bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-900/40 hover:bg-rose-50' }}">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Ada Alpa ({{ collect($rekap)->filter(fn($x)=>$x['A']>0)->count() }})
                </button>

                <button type="button" wire:click="$set('filterStatus', 'izin_sakit')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border
                               {{ $filterStatus === 'izin_sakit' 
                                   ? 'bg-amber-500 text-white border-amber-500 shadow-sm' 
                                   : 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-900/40 hover:bg-amber-50' }}">
                    <i class="bi bi-bandaid me-1"></i> Ada Sakit/Izin ({{ collect($rekap)->filter(fn($x)=>($x['I']+$x['S'])>0)->count() }})
                </button>

                <button type="button" wire:click="$set('filterStatus', 'kurang')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border
                               {{ $filterStatus === 'kurang' 
                                   ? 'bg-purple-500 text-white border-purple-500 shadow-sm' 
                                   : 'bg-white dark:bg-slate-800 text-purple-600 dark:text-purple-400 border-purple-200 dark:border-purple-900/40 hover:bg-purple-50' }}">
                    <i class="bi bi-percent me-1"></i> Kehadiran < 100% ({{ collect($rekap)->filter(fn($x)=>$x['pct']<100)->count() }})
                </button>

                <div class="ms-auto text-xs text-slate-500 dark:text-slate-400">
                    Klik pada sel nilai untuk mengubah status: 
                    <span class="badge bg-emerald-500/20 text-emerald-600 font-bold">H</span> 
                    <span class="badge bg-sky-500/20 text-sky-600 font-bold">I</span> 
                    <span class="badge bg-amber-500/20 text-amber-600 font-bold">S</span> 
                    <span class="badge bg-rose-500/20 text-rose-600 font-bold">A</span>
                </div>
            </div>
        @endif
    </div>

    @if ($pembelajaran_id && count($rekap) > 0)
        {{-- STATS CARDS (SUMMARY METRICS) --}}
        <div class="d-print-none grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            {{-- Total Siswa --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-cyan-500 text-white d-flex align-items-center justify-content-center shadow-xs">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Siswa</div>
                        <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $stats['total_siswa'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Tingkat Kehadiran --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-emerald-500/20 shadow-xs">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-500 text-white d-flex align-items-center justify-content-center shadow-xs">
                        <i class="bi bi-pie-chart-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">% Kehadiran</div>
                        <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-display">{{ $stats['persentase_hadir'] }}%</div>
                    </div>
                </div>
            </div>

            {{-- Hadir Total --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-emerald-500/20 shadow-xs">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 d-flex align-items-center justify-content-center font-bold">
                        H
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Hadir</div>
                        <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $stats['total_h'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Izin Total --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/15 text-sky-600 dark:text-sky-400 d-flex align-items-center justify-content-center font-bold">
                        I
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Izin</div>
                        <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $stats['total_i'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Sakit Total --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-amber-500/20 shadow-xs">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 d-flex align-items-center justify-content-center font-bold">
                        S
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Sakit</div>
                        <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $stats['total_s'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Alpa Total --}}
            <div class="glass-panel p-3 rounded-2xl border border-slate-200/90 dark:border-rose-500/20 shadow-xs {{ $stats['total_a'] > 0 ? 'bg-rose-500/5' : '' }}">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400 d-flex align-items-center justify-content-center font-bold">
                        A
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Total Alpa</div>
                        <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 font-display">{{ $stats['total_a'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MATRIX TABLE CARD --}}
        <div class="glass-panel rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden mb-4">
            {{-- Header info bar --}}
            <div class="p-3 px-4 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200/90 dark:border-slate-700/60 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-pill px-2.5 py-1">
                        {{ $selectedPembelajaran->keterangan ?? '-' }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Periode: <strong>{{ Carbon::parse($bulan . '-01')->locale('id_ID')->isoFormat('MMMM YYYY') }}</strong> ({{ count($tglList) }} Hari)
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2 text-xs text-slate-500">
                    <span>Menampilkan <strong>{{ $this->filteredRekap->count() }}</strong> dari <strong>{{ count($rekap) }}</strong> siswa</span>
                </div>
            </div>

            <div class="matrix-wrapper" style="overflow-x: auto; max-height: 68vh;">
                <table class="table table-bordered table-sm m-0 matrix-table align-middle" x-data="{
                    statusCycle: ['-', 'H', 'I', 'S', 'A'],
                    cycleCell(siswaId, date, current) {
                        const idx = this.statusCycle.indexOf(current);
                        const next = this.statusCycle[(idx + 1) % this.statusCycle.length];
                        $wire.updateCell(siswaId, date, next);
                    }
                }">
                    <thead class="sticky-thead">
                        <tr>
                            <th class="sticky-col-no text-center py-2" style="width: 36px; min-width: 36px;">No</th>
                            <th class="sticky-col-nama py-2 px-3" style="min-width: 220px;">Nama Siswa</th>
                            @foreach ($tglList as $t)
                                @php
                                    $colBg = $t['is_today'] ? 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300' : ($t['is_sunday'] ? 'bg-slate-100/80 dark:bg-slate-800 text-rose-500' : '');
                                @endphp
                                <th class="text-center p-1 font-mono {{ $colBg }}" 
                                    style="width: 32px; min-width: 32px;"
                                    title="{{ $t['date'] }} ({{ $t['day_name'] }})">
                                    <div class="text-[9px] uppercase tracking-tighter opacity-75">{{ $t['day_name'] }}</div>
                                    <div class="text-xs font-bold leading-tight">{{ $t['day'] }}</div>
                                </th>
                            @endforeach
                            <th class="text-center font-bold text-success bg-light dark:bg-slate-800" style="width: 36px; min-width: 36px;" title="Total Hadir">H</th>
                            <th class="text-center font-bold text-info bg-light dark:bg-slate-800" style="width: 36px; min-width: 36px;" title="Total Izin">I</th>
                            <th class="text-center font-bold text-warning bg-light dark:bg-slate-800" style="width: 36px; min-width: 36px;" title="Total Sakit">S</th>
                            <th class="text-center font-bold text-danger bg-light dark:bg-slate-800" style="width: 36px; min-width: 36px;" title="Total Alpa">A</th>
                            <th class="text-center font-bold bg-light dark:bg-slate-800" style="width: 60px; min-width: 60px;" title="Persentase Kehadiran">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->filteredRekap as $r)
                            <tr wire:key="row-{{ $r['id'] }}" class="matrix-row">
                                <td class="sticky-col-no text-center text-xs text-slate-400 font-mono">{{ $loop->iteration }}</td>
                                <td class="sticky-col-nama text-nowrap px-3 py-1.5">
                                    <div class="font-bold text-xs text-slate-800 dark:text-slate-100">{!! $r['nama_processed'] !!}</div>
                                    @if ($r['nis'] !== '-')
                                        <div class="text-[10px] text-slate-400 font-mono">NIS: {{ $r['nis'] }}</div>
                                    @endif
                                </td>

                                @foreach ($tglList as $t)
                                    @php
                                        $d = $t['date'];
                                        $st = $r['tgl'][$d] ?? '-';
                                    @endphp
                                    <td class="p-0 text-center cell-presensi status-cell-{{ $st }} {{ $t['is_today'] ? 'cell-today' : '' }}"
                                        @click="cycleCell('{{ $r['id'] }}', '{{ $d }}', '{{ $st }}')"
                                        title="{{ $r['nama_raw'] }} • {{ Carbon::parse($d)->translatedFormat('d M Y') }} • Status: {{ $st == '-' ? 'Belum Ada' : ($st == 'H' ? 'Hadir' : ($st == 'I' ? 'Izin' : ($st == 'S' ? 'Sakit' : 'Alpa'))) }}"
                                        wire:loading.class="opacity-50">
                                        <span class="cell-badge">{{ $st }}</span>
                                    </td>
                                @endforeach

                                <td class="text-center font-extrabold text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-500/5">{{ $r['H'] }}</td>
                                <td class="text-center font-extrabold text-xs text-sky-600 dark:text-sky-400 bg-sky-500/5">{{ $r['I'] }}</td>
                                <td class="text-center font-extrabold text-xs text-amber-600 dark:text-amber-400 bg-amber-500/5">{{ $r['S'] }}</td>
                                <td class="text-center font-extrabold text-xs text-rose-600 dark:text-rose-400 bg-rose-500/5 {{ $r['A'] > 0 ? 'bg-rose-500/20 text-rose-700' : '' }}">{{ $r['A'] }}</td>
                                <td class="text-center font-mono text-xs font-bold bg-slate-50 dark:bg-slate-800/40">
                                    <span class="badge {{ $r['pct'] >= 90 ? 'bg-success/15 text-success' : ($r['pct'] >= 75 ? 'bg-warning/15 text-warning' : 'bg-danger/15 text-danger') }} rounded-pill">
                                        {{ $r['pct'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($tglList) + 7 }}" class="py-5 text-center text-slate-400">
                                    <i class="bi bi-search fs-2 d-block mb-2"></i>
                                    Tidak ada data siswa yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="sticky-tfoot d-print-none">
                        <tr class="fw-bold text-xs bg-slate-100 dark:bg-slate-800">
                            <td colspan="2" class="sticky-col-nama px-3 py-2 text-end uppercase tracking-wider font-display">
                                Total Hadir Harian:
                            </td>
                            @foreach ($tglList as $t)
                                @php
                                    $cntH = $dailyTotals[$t['date']]['H'] ?? 0;
                                @endphp
                                <td class="text-center p-1 font-mono text-[11px] {{ $cntH > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-400' }}">
                                    {{ $cntH > 0 ? $cntH : '-' }}
                                </td>
                            @endforeach
                            <td class="text-center text-emerald-600 font-bold">{{ $stats['total_h'] }}</td>
                            <td class="text-center text-sky-600 font-bold">{{ $stats['total_i'] }}</td>
                            <td class="text-center text-amber-600 font-bold">{{ $stats['total_s'] }}</td>
                            <td class="text-center text-rose-600 font-bold">{{ $stats['total_a'] }}</td>
                            <td class="text-center font-bold text-primary">{{ $stats['persentase_hadir'] }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- PRINT SIGNATURE FOOTER --}}
        <div class="d-none d-print-block mt-5 pt-3">
            <div class="row text-center">
                <div class="col-6">
                    <p class="mb-5">Mengetahui,<br><strong>Kepala Sekolah</strong></p>
                    <p class="mt-5 mb-0 fw-bold underline">___________________________</p>
                    <small>NIP. -</small>
                </div>
                <div class="col-6">
                    <p class="mb-5">Tanggal Cetak: {{ Carbon::now()->translatedFormat('d F Y') }}<br><strong>Guru Pengampu</strong></p>
                    <p class="mt-5 mb-0 fw-bold underline">___________________________</p>
                    <small>{{ $selectedPembelajaran->jadwal->first()?->user->name ?? 'Guru Mapel' }}</small>
                </div>
            </div>
        </div>
    @elseif (!$pembelajaran_id)
        {{-- EMPTY STATE: NO PEMBELAJARAN SELECTED --}}
        <div class="glass-panel p-5 rounded-3xl border border-slate-200/90 dark:border-cyan-500/20 text-center my-4 shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-500/15 via-cyan-500/15 to-emerald-500/15 text-cyan-600 dark:text-cyan-400 d-inline-flex align-items-center justify-center text-3xl mb-3 shadow-inner">
                <i class="bi bi-clock-history"></i>
            </div>
            <h5 class="font-extrabold text-slate-800 dark:text-slate-100 font-display mb-1">Pilih Pembelajaran Terlebih Dahulu</h5>
            <p class="text-slate-500 dark:text-slate-400 text-sm max-w-md mx-auto mb-4">
                Silakan pilih salah satu mata pelajaran / kelas pada menu dropdown di atas untuk melihat tabel rekap presensi bulanan secara menyeluruh.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                @foreach ($this->pembelajaranList->take(6) as $p)
                    <button type="button" wire:click="$set('pembelajaran_id', '{{ $p->id }}')"
                            class="btn btn-sm btn-outline-primary rounded-xl font-semibold shadow-xs">
                        <i class="bi bi-arrow-right-short"></i> {{ $p->keterangan }}
                    </button>
                @endforeach
            </div>
        </div>
    @else
        {{-- EMPTY STATE: NO STUDENTS ENROLLED --}}
        <div class="glass-panel p-5 rounded-3xl border border-amber-500/30 text-center my-4 shadow-sm bg-amber-500/5">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-600 d-inline-flex align-items-center justify-center text-3xl mb-3">
                <i class="bi bi-people"></i>
            </div>
            <h5 class="font-extrabold text-slate-800 dark:text-slate-100 font-display mb-1">Belum Ada Anggota Siswa</h5>
            <p class="text-slate-500 dark:text-slate-400 text-sm max-w-md mx-auto mb-3">
                Pembelajaran ini belum memiliki siswa yang terdaftar sebagai anggota kelas aktif.
            </p>
            <a href="{{ route('pembelajaran.anggota.index', $pembelajaran_id) }}" wire:navigate class="btn btn-sm btn-warning rounded-xl font-bold shadow-xs">
                <i class="bi bi-person-plus me-1"></i> Kelola Anggota Siswa
            </a>
        </div>
    @endif

    {{-- STYLESHEET FOR MATRIX AND PRINT --}}
    <style>
        .matrix-table {
            border-collapse: separate !important;
            border-spacing: 0;
            width: max-content;
            min-width: 100%;
        }

        .sticky-thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #f8fafc;
            border-bottom: 2px solid #cbd5e1 !important;
        }

        [data-theme="dark"] .sticky-thead th {
            background: #0f172a !important;
            border-bottom: 2px solid #334155 !important;
            color: #f1f5f9;
        }

        .sticky-col-no {
            position: sticky;
            left: 0;
            z-index: 10;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
        }

        .sticky-col-nama {
            position: sticky;
            left: 36px;
            z-index: 10;
            background: #ffffff;
            border-right: 2px solid #cbd5e1 !important;
            box-shadow: 2px 0 5px rgba(0,0,0,0.03);
        }

        [data-theme="dark"] .sticky-col-no,
        [data-theme="dark"] .sticky-col-nama {
            background: #0b1524 !important;
            border-color: #334155 !important;
        }

        .sticky-thead .sticky-col-no,
        .sticky-thead .sticky-col-nama {
            z-index: 30;
            background: #f1f5f9 !important;
        }

        [data-theme="dark"] .sticky-thead .sticky-col-no,
        [data-theme="dark"] .sticky-thead .sticky-col-nama {
            background: #111e33 !important;
        }

        .sticky-tfoot td {
            position: sticky;
            bottom: 0;
            z-index: 20;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.03);
        }

        .sticky-tfoot .sticky-col-nama {
            z-index: 25;
        }

        .cell-presensi {
            cursor: pointer;
            touch-action: manipulation;
            user-select: none;
            transition: transform 0.1s ease, background-color 0.15s ease;
            height: 38px;
            position: relative;
        }

        .cell-presensi:hover {
            transform: scale(1.15);
            z-index: 15;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            border-radius: 6px;
        }

        .cell-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .status-cell-H .cell-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }

        .status-cell-I .cell-badge {
            background: rgba(2, 132, 199, 0.15);
            color: #0284c7;
        }

        .status-cell-S .cell-badge {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .status-cell-A .cell-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
            font-weight: 800;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.3);
        }

        .status-cell-- .cell-badge {
            color: #94a3b8;
            opacity: 0.4;
        }

        .cell-today {
            background-color: rgba(6, 182, 212, 0.06);
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 9pt;
            }

            header,
            #sidebar,
            .offcanvas,
            .d-print-none,
            .btn,
            nav {
                display: none !important;
            }

            main {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .matrix-wrapper {
                overflow: visible !important;
                max-height: none !important;
            }

            .matrix-table {
                width: 100% !important;
                font-size: 7.5pt !important;
                border-collapse: collapse !important;
            }

            .matrix-table th,
            .matrix-table td {
                border: 1px solid #333333 !important;
                padding: 2px !important;
                background: #ffffff !important;
                color: #000000 !important;
                position: static !important;
                box-shadow: none !important;
            }

            .cell-badge {
                color: #000000 !important;
                background: transparent !important;
                box-shadow: none !important;
                font-size: 7.5pt;
            }

            .glass-panel {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
            }
        }
    </style>
</div>
