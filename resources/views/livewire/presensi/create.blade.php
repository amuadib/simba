<?php

use App\Models\Pembelajaran;
use App\Models\Presensi;
use App\Models\Jurnal;
use App\Models\Siswa;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public $pembelajaran;
    public $tanggal;
    public $search = '';
    public $sort = 'nama'; // 'nama' atau 'panggilan'
    public $pengajarNama = '';
    public $rombelNama = '';

    public $statusMap = []; // [siswa_id => 'H'|'I'|'S'|'A'|null]
    public $keteranganMap = []; // [siswa_id => string]
    public $openNoteId = null;

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->pembelajaran = $pembelajaran;
        $this->tanggal = request()->tanggal ?? date('Y-m-d');

        // Pengajar & Rombel
        $jadwal = $this->pembelajaran->jadwal()->with('user')->first();
        $this->pengajarNama = $jadwal?->user?->name ?? auth()->user()->name ?? 'Bapak/Ibu Guru';
        
        $firstAnggota = $this->pembelajaran->anggota()->with('siswa.rombel')->first();
        $this->rombelNama = $firstAnggota?->siswa?->rombel?->nama ?? $this->pembelajaran->keterangan;

        $this->loadPresensi();
    }

    public function updatedTanggal()
    {
        $this->loadPresensi();
    }

    public function previousDate()
    {
        $this->tanggal = Carbon::parse($this->tanggal)->subDay()->format('Y-m-d');
        $this->loadPresensi();
    }

    public function nextDate()
    {
        $this->tanggal = Carbon::parse($this->tanggal)->addDay()->format('Y-m-d');
        $this->loadPresensi();
    }

    public function todayDate()
    {
        $this->tanggal = date('Y-m-d');
        $this->loadPresensi();
    }

    public function toggleSort()
    {
        $this->sort = $this->sort === 'nama' ? 'panggilan' : 'nama';
        $label = $this->sort === 'nama' ? 'Nama Lengkap' : 'Nama Panggilan';
        $this->dispatch('toast', message: 'Urutan diubah ke ' . $label, type: 'info');
    }

    public function loadPresensi()
    {
        $presensi = Presensi::where('pembelajaran_id', $this->pembelajaran->id)
            ->where('tanggal', $this->tanggal)
            ->get()
            ->keyBy('siswa_id');

        $anggota = $this->sort === 'panggilan'
            ? $this->pembelajaran->anggotaSortPanggilan()->with('siswa.rombel')->get()
            : $this->pembelajaran->anggota()->with('siswa.rombel')->get();

        $this->statusMap = [];
        $this->keteranganMap = [];

        foreach ($anggota as $item) {
            $s = $item->siswa;
            if ($s) {
                $this->statusMap[$s->id] = $presensi[$s->id]->status ?? null;
                $this->keteranganMap[$s->id] = $presensi[$s->id]->keterangan ?? '';
            }
        }
    }

    public function setStatus($siswaId, $status)
    {
        $current = $this->statusMap[$siswaId] ?? null;
        $next = ($current === $status) ? null : $status;

        $this->statusMap[$siswaId] = $next;

        if ($next === null) {
            Presensi::where('pembelajaran_id', $this->pembelajaran->id)
                ->where('siswa_id', $siswaId)
                ->where('tanggal', $this->tanggal)
                ->delete();

            $siswa = Siswa::find($siswaId);
            $nama = $siswa ? ($siswa->panggilan ?? $siswa->nama) : 'Siswa';
            $this->dispatch('toast', message: 'Status presensi ' . $nama . ' direset', type: 'info');
        } else {
            Presensi::updateOrCreate(
                [
                    'pembelajaran_id' => $this->pembelajaran->id,
                    'siswa_id' => $siswaId,
                    'tanggal' => $this->tanggal,
                ],
                [
                    'status' => $next,
                    'keterangan' => $this->keteranganMap[$siswaId] ?? null,
                ]
            );

            $siswa = Siswa::find($siswaId);
            $nama = $siswa ? ($siswa->panggilan ?? $siswa->nama) : 'Siswa';
            $label = match ($next) {
                'H' => 'Hadir',
                'I' => 'Izin',
                'S' => 'Sakit',
                'A' => 'Alpa',
                default => $next,
            };
            $this->dispatch('toast', message: $nama . ': ' . $label, type: 'success');
        }
    }

    public function setBulkStatus($status)
    {
        $students = $this->getFilteredStudentsProperty();
        if ($students->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($students as $s) {
            $this->statusMap[$s->id] = $status;
            $rows[] = [
                'id' => (string) Str::uuid(),
                'pembelajaran_id' => $this->pembelajaran->id,
                'siswa_id' => $s->id,
                'tanggal' => $this->tanggal,
                'status' => $status,
                'keterangan' => $this->keteranganMap[$s->id] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            Presensi::upsert($rows, ['siswa_id', 'pembelajaran_id', 'tanggal'], ['status', 'keterangan', 'updated_at']);
        }

        $label = match ($status) {
            'H' => 'Hadir',
            'I' => 'Izin',
            'S' => 'Sakit',
            'A' => 'Alpa',
            default => $status,
        };

        $this->dispatch('toast', message: 'Semua siswa diubah ke ' . $label . ' (' . count($rows) . ' siswa)', type: 'success');
    }

    public function toggleNote($siswaId)
    {
        $this->openNoteId = ($this->openNoteId === $siswaId) ? null : $siswaId;
    }

    public function saveKeterangan($siswaId)
    {
        $ket = trim($this->keteranganMap[$siswaId] ?? '');
        $status = $this->statusMap[$siswaId] ?? 'H';

        Presensi::updateOrCreate(
            [
                'pembelajaran_id' => $this->pembelajaran->id,
                'siswa_id' => $siswaId,
                'tanggal' => $this->tanggal,
            ],
            [
                'status' => $status,
                'keterangan' => $ket ?: null,
            ]
        );

        $this->openNoteId = null;
        $this->dispatch('toast', message: 'Keterangan presensi berhasil disimpan', type: 'success');
    }

    public function getFilteredStudentsProperty()
    {
        $query = $this->sort === 'panggilan'
            ? $this->pembelajaran->anggotaSortPanggilan()->with('siswa.rombel')->get()
            : $this->pembelajaran->anggota()->with('siswa.rombel')->get();

        $students = $query->map(fn($a) => $a->siswa)->filter();

        if (!empty(trim($this->search))) {
            $keyword = strtolower(trim($this->search));
            $students = $students->filter(function ($s) use ($keyword) {
                return str_contains(strtolower($s->nama ?? ''), $keyword)
                    || str_contains(strtolower($s->panggilan ?? ''), $keyword)
                    || str_contains(strtolower($s->nis ?? ''), $keyword)
                    || str_contains(strtolower($s->nisn ?? ''), $keyword);
            });
        }

        return $students->values();
    }

    public function with()
    {
        $countH = 0;
        $countI = 0;
        $countS = 0;
        $countA = 0;

        foreach ($this->statusMap as $st) {
            if ($st === 'H') $countH++;
            elseif ($st === 'I') $countI++;
            elseif ($st === 'S') $countS++;
            elseif ($st === 'A') $countA++;
        }

        $jurnalHariIni = Jurnal::where('pembelajaran_id', $this->pembelajaran->id)
            ->where('tanggal', $this->tanggal)
            ->first();

        // Pengecekan kesesuaian jadwal (hari & jam)
        $now = Carbon::now();
        $isHariIni = ($this->tanggal === date('Y-m-d'));
        $dayOfWeekIso = Carbon::parse($this->tanggal)->dayOfWeekIso;

        $jadwalHariTerpilih = $this->pembelajaran->jadwal
            ->first(fn($j) => (int)$j->hari === (int)$dayOfWeekIso);

        $activeJadwal = null;
        $isSesiBerjalan = false;

        if ($isHariIni) {
            $currentHari = $now->dayOfWeekIso;
            $currentTime = $now->format('H:i');

            foreach ($this->pembelajaran->jadwal as $j) {
                if ((int)$j->hari === (int)$currentHari && $j->jam_mulai && $j->jam_selesai) {
                    $mulai = date('H:i', strtotime($j->jam_mulai));
                    $selesai = date('H:i', strtotime($j->jam_selesai));

                    if ($currentTime >= $mulai && $currentTime <= $selesai) {
                        $activeJadwal = $j;
                        $isSesiBerjalan = true;
                        break;
                    }
                }
            }
        }

        return [
            'students' => $this->filteredStudents,
            'countH' => $countH,
            'countI' => $countI,
            'countS' => $countS,
            'countA' => $countA,
            'totalSantri' => count($this->statusMap),
            'jurnalHariIni' => $jurnalHariIni,
            'isSesiBerjalan' => $isSesiBerjalan,
            'activeJadwal' => $activeJadwal,
            'jadwalHariTerpilih' => $jadwalHariTerpilih,
        ];
    }
};

?>

<div>
    {{-- STANDARD PAGE HEADER (SESUAI HALAMAN LAINNYA) --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Presensi Kelas</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                Input kehadiran siswa: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong> &bull; Pengajar: <strong class="text-slate-700 dark:text-slate-200">{{ $pengajarNama }}</strong>
            </p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- ACTION BAR NAVIGASI --}}
    <div class="flex items-center justify-between gap-2.5 flex-wrap mb-4">
        <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Pembelajaran</span>
        </a>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran->id) }}" wire:navigate class="btn btn-outline-primary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-journal-text"></i>
                <span>Jurnal Kelas</span>
            </a>

            <a href="{{ route('pembelajaran.presensi.index', ['pembelajaran_id' => $pembelajaran->id]) }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-clock-history"></i>
                <span>Rekap Presensi</span>
            </a>
        </div>
    </div>

    {{-- KARTU SESI PERTEMUAN (BAGIAN DARI GAMBAR: HARI, TANGGAL, MAPEL, STATUS BERJALAN & MODE SISWA) --}}
    <div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 text-white shadow-xl mb-6 relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-36 h-36 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
            {{-- Info Tanggal & Pembelajaran --}}
            <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white text-2xl flex-shrink-0 shadow-inner border border-white/20 mt-0.5 sm:mt-0">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-blue-100 font-display">
                            {{ strtoupper(\Carbon\Carbon::parse($tanggal)->locale('id_ID')->isoFormat('dddd, DD MMMM YYYY')) }}
                        </span>
                        
                        @if ($isSesiBerjalan)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-white shadow-xs"
                                  title="Sesi aktif sesuai jadwal: {{ date('H:i', strtotime($activeJadwal->jam_mulai)) }} - {{ date('H:i', strtotime($activeJadwal->jam_selesai)) }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                                SESI BERJALAN
                            </span>
                        @endif
                    </div>
                    <h3 class="text-base sm:text-lg font-extrabold text-white truncate m-0 mt-0.5 font-display">
                        {{ $pembelajaran->keterangan }}
                    </h3>
                    @if ($jadwalHariTerpilih)
                        <div class="text-[11px] text-blue-200/90 font-medium flex items-center gap-1.5 mt-0.5">
                            <i class="bi bi-clock-history"></i>
                            <span>Jadwal: {{ date('H:i', strtotime($jadwalHariTerpilih->jam_mulai)) }} - {{ date('H:i', strtotime($jadwalHariTerpilih->jam_selesai)) }}</span>
                        </div>
                    @endif

                    @if ($jurnalHariIni && !empty(trim($jurnalHariIni->materi)))
                        <div class="text-xs text-white/95 font-medium flex items-start gap-1.5 mt-2 bg-white/10 backdrop-blur-xs rounded-xl px-2.5 py-1.5 border border-white/15 max-w-xl">
                            <i class="bi bi-journal-text text-amber-300 flex-shrink-0 mt-0.5 text-sm"></i>
                            <div class="leading-snug break-words min-w-0">
                                <span class="text-blue-200 text-[11px] block sm:inline font-normal">Materi: </span>
                                <strong class="text-white">{{ $jurnalHariIni->materi }}</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Navigasi Tanggal & Mode Siswa --}}
            <div class="flex items-center gap-2 flex-wrap">
                <button wire:click="toggleSort"
                        class="px-3.5 py-2 rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-md text-white text-xs font-bold inline-flex items-center gap-1.5 transition border border-white/20 shadow-xs"
                        title="Ubah urutan: {{ $sort === 'nama' ? 'Nama Panggilan' : 'Nama Lengkap' }}">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>Mode Siswa ({{ $sort === 'nama' ? 'L' : 'P' }})</span>
                </button>

                <div class="flex items-center bg-white/20 backdrop-blur-md rounded-xl p-1 border border-white/20">
                    <button wire:click="previousDate" class="px-2 py-1 hover:bg-white/20 rounded-lg text-white text-xs transition" title="Hari Sebelumnya">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="date" wire:model.live="tanggal"
                           class="bg-transparent text-white text-xs px-2 py-0.5 border-0 focus:outline-none cursor-pointer">
                    <button wire:click="nextDate" class="px-2 py-1 hover:bg-white/20 rounded-lg text-white text-xs transition" title="Hari Berikutnya">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                @if ($tanggal !== date('Y-m-d'))
                    <button wire:click="todayDate" class="btn btn-light btn-sm rounded-xl px-3 py-1.5 text-xs font-bold shadow-xs">
                        Hari Ini
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- 4 KARTU STATISTIK RINGKASAN (HADIR, IZIN, SAKIT, ALPA - SESUAI GAMBAR) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
        {{-- HADIR --}}
        <div class="bg-[#f0fdf4] dark:bg-emerald-950/30 border border-emerald-200/90 dark:border-emerald-800/60 rounded-2xl p-3 sm:p-4 text-center shadow-xs transition hover:shadow-md">
            <div class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider font-display">HADIR</div>
            <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1 font-display">{{ $countH }}</div>
        </div>

        {{-- IZIN --}}
        <div class="bg-[#fffbeb] dark:bg-amber-950/30 border border-amber-200/90 dark:border-amber-800/60 rounded-2xl p-3 sm:p-4 text-center shadow-xs transition hover:shadow-md">
            <div class="text-xs font-extrabold text-[#d97706] dark:text-amber-400 uppercase tracking-wider font-display">IZIN</div>
            <div class="text-2xl sm:text-3xl font-black text-[#d97706] dark:text-amber-400 mt-1 font-display">{{ $countI }}</div>
        </div>

        {{-- SAKIT --}}
        <div class="bg-[#f0f9ff] dark:bg-sky-950/30 border border-sky-200/90 dark:border-sky-800/60 rounded-2xl p-3 sm:p-4 text-center shadow-xs transition hover:shadow-md">
            <div class="text-xs font-extrabold text-[#0284c7] dark:text-sky-400 uppercase tracking-wider font-display">SAKIT</div>
            <div class="text-2xl sm:text-3xl font-black text-[#0284c7] dark:text-sky-400 mt-1 font-display">{{ $countS }}</div>
        </div>

        {{-- ALPA --}}
        <div class="bg-[#fef2f2] dark:bg-rose-950/30 border border-rose-200/90 dark:border-rose-800/60 rounded-2xl p-3 sm:p-4 text-center shadow-xs transition hover:shadow-md">
            <div class="text-xs font-extrabold text-[#e11d48] dark:text-rose-400 uppercase tracking-wider font-display">ALPA</div>
            <div class="text-2xl sm:text-3xl font-black text-[#e11d48] dark:text-rose-400 mt-1 font-display">{{ $countA }}</div>
        </div>
    </div>

    {{-- MAIN GLASS PANEL DAFTAR PRESENSI (STANDARD SIMBA) --}}
    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl space-y-4">
        
        {{-- TOOLBAR PENCARIAN & BULK ACTIONS (SESUAI GAMBAR) --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-200/80 dark:border-slate-800">
            {{-- Search Bar --}}
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="bi bi-search text-xs sm:text-sm"></i>
                </div>
                <input type="text"
                       wire:model.live.debounce.200ms="search"
                       class="form-control rounded-2xl pl-10 pr-9 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs sm:text-sm py-2 shadow-2xs"
                       placeholder="Cari nama atau NIS siswa...">
                @if ($search)
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="bi bi-x-circle-fill text-xs"></i>
                    </button>
                @endif
            </div>

            {{-- Bulk Actions: Ubah Semua ke --}}
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 p-2 sm:p-2.5 flex items-center gap-2 shadow-2xs">
                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 font-display pl-1">
                    Ubah Semua ke:
                </span>
                <div class="flex items-center gap-1.5">
                    <button wire:click="setBulkStatus('H')"
                            class="px-3 py-1 rounded-full text-xs font-black bg-[#059669] hover:bg-[#047857] text-white shadow-xs transition active:scale-95">
                        Hadir
                    </button>
                    <button wire:click="setBulkStatus('I')"
                            class="px-3 py-1 rounded-full text-xs font-black bg-[#d97706] hover:bg-[#b45309] text-white shadow-xs transition active:scale-95">
                        Izin
                    </button>
                    <button wire:click="setBulkStatus('S')"
                            class="px-3 py-1 rounded-full text-xs font-black bg-[#0284c7] hover:bg-[#0369a1] text-white shadow-xs transition active:scale-95">
                        Sakit
                    </button>
                    <button wire:click="setBulkStatus('A')"
                            class="px-3 py-1 rounded-full text-xs font-black bg-[#e11d48] hover:bg-[#be123c] text-white shadow-xs transition active:scale-95">
                        Alpa
                    </button>
                </div>
            </div>
        </div>

        {{-- DAFTAR KARTU SANTRI (BAGIAN DARI GAMBAR: NO, NAMA, NIS & TOMBOL H/I/S/A) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @forelse ($students as $idx => $s)
                @php
                    $currentStatus = $statusMap[$s->id] ?? null;
                    $hasNote = !empty($keteranganMap[$s->id]);
                    $isNoteOpen = ($openNoteId === $s->id);
                @endphp
                <div wire:key="student-row-{{ $s->id }}"
                     class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3 sm:p-3.5 shadow-xs hover:shadow-md transition">
                    <div class="flex items-center justify-between gap-2.5 sm:gap-3">
                        {{-- Left: Nomor & Info Siswa --}}
                        <div class="flex items-start sm:items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold flex items-center justify-center text-xs sm:text-sm flex-shrink-0 font-display mt-0.5 sm:mt-0">
                                {{ $idx + 1 }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h5 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 m-0 font-display leading-snug break-words">
                                    {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                </h5>
                                <div class="text-[11px] text-slate-400 font-medium flex items-center gap-2 mt-0.5">
                                    <span>NIS: {{ $s->nis ?? $s->nisn ?? '-' }}</span>
                                    @if ($hasNote)
                                        <span class="text-amber-500 font-semibold flex items-center gap-1 cursor-pointer hover:underline" wire:click="toggleNote('{{ $s->id }}')">
                                            <i class="bi bi-chat-text-fill"></i>
                                            <span class="truncate max-w-[120px]">{{ $keteranganMap[$s->id] }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Right: Tombol Status [H] [I] [S] [A] Sesuai Gambar --}}
                        <div class="flex items-center gap-1 sm:gap-1.5 flex-shrink-0">
                            {{-- H --}}
                            <button wire:click="setStatus('{{ $s->id }}', 'H')"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center text-xs font-black transition active:scale-95 {{ $currentStatus === 'H' ? 'bg-[#059669] text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                    title="Tandai Hadir">
                                H
                            </button>

                            {{-- I --}}
                            <button wire:click="setStatus('{{ $s->id }}', 'I')"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center text-xs font-black transition active:scale-95 {{ $currentStatus === 'I' ? 'bg-[#d97706] text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                    title="Tandai Izin">
                                I
                            </button>

                            {{-- S --}}
                            <button wire:click="setStatus('{{ $s->id }}', 'S')"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center text-xs font-black transition active:scale-95 {{ $currentStatus === 'S' ? 'bg-[#0284c7] text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                    title="Tandai Sakit">
                                S
                            </button>

                            {{-- A --}}
                            <button wire:click="setStatus('{{ $s->id }}', 'A')"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center text-xs font-black transition active:scale-95 {{ $currentStatus === 'A' ? 'bg-[#e11d48] text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                    title="Tandai Alpa">
                                A
                            </button>

                            {{-- Keterangan Toggle --}}
                            <button wire:click="toggleNote('{{ $s->id }}')"
                                    class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 flex items-center justify-center text-xs ml-0.5 transition"
                                    title="Tambah atau ubah catatan/alasan">
                                <i class="bi {{ $hasNote || $isNoteOpen ? 'bi-chat-left-text-fill text-blue-500' : 'bi-chat-left' }}"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Collapsible Keterangan Input Box --}}
                    @if ($isNoteOpen)
                        <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-2">
                            <input type="text"
                                   wire:model="keteranganMap.{{ $s->id }}"
                                   wire:keydown.enter="saveKeterangan('{{ $s->id }}')"
                                   placeholder="Tulis keterangan (misal: surat dokter, izin urusan keluarga)..."
                                   class="form-control form-control-sm rounded-xl bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <button wire:click="saveKeterangan('{{ $s->id }}')" class="btn btn-primary btn-sm rounded-xl px-3 py-1 text-xs font-bold">
                                Simpan
                            </button>
                            <button wire:click="toggleNote('{{ $s->id }}')" class="btn btn-outline-secondary btn-sm rounded-xl px-2.5 py-1 text-xs font-semibold">
                                Batal
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-8 text-center text-slate-400 text-xs">
                    <i class="bi bi-people text-3xl d-block mb-2 text-slate-300 dark:text-slate-600"></i>
                    Tidak ada siswa yang ditemukan untuk pencarian ini.
                </div>
            @endforelse
        </div>

        {{-- BOTTOM TOOLBAR & JUMP TO JURNAL / NILAI --}}
        <div class="mt-6 pt-4 border-t border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500 dark:text-slate-400">
                Total <strong>{{ $totalSantri }}</strong> siswa terdaftar dalam kelas ini.
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                @if ($jurnalHariIni)
                    <a href="{{ route('pembelajaran.jurnal.nilai.create', [$pembelajaran->id, $jurnalHariIni->id]) }}" wire:navigate
                       class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition">
                        <i class="bi bi-award"></i>
                        <span>Input Nilai Jurnal Pertemuan Ini</span>
                    </a>
                @else
                    <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran->id) }}" wire:navigate
                       class="btn btn-outline-primary btn-sm rounded-xl px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5 shadow-xs">
                        <i class="bi bi-journal-plus"></i>
                        <span>Buat Jurnal Pembelajaran</span>
                    </a>
                @endif
            </div>
        </div>

    </div>
</div>
