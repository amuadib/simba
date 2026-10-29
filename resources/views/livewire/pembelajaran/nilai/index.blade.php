<?php

use App\Models\Pembelajaran;
use App\Models\Jurnal;
use App\Models\Nilai;
use App\Models\Presensi;
use App\Exports\NilaiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $pembelajaran;

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->pembelajaran = $pembelajaran;
    }

    public function with()
    {
        $nilai = [];
        $jurnals = Jurnal::where('pembelajaran_id', $this->pembelajaran->id)->orderBy('tanggal')->get();

        $anggotaList = $this->pembelajaran->anggota()->with('siswa.rombel')->get();

        // Get all scores for this pembelajaran
        $allNilai = Nilai::whereIn('jurnal_id', $jurnals->pluck('id'))->get();

        foreach ($allNilai as $n) {
            $nilai[$n->siswa_id][$n->jurnal_id] = $n->nilai;
        }

        // Get all presensi for this pembelajaran
        $allPresensi = Presensi::where('pembelajaran_id', $this->pembelajaran->id)->get();
        $presensiMap = [];
        foreach ($allPresensi as $p) {
            $dateKey = \Carbon\Carbon::parse($p->tanggal)->format('Y-m-d');
            $presensiMap[$p->siswa_id][$dateKey] = $p->status;
        }

        return [
            'jurnals' => $jurnals,
            'anggotaList' => $anggotaList->sortBy(fn($a) => $a->siswa->nama),
            'nilaiMap' => $nilai,
            'presensiMap' => $presensiMap,
        ];
    }

    public function export()
    {
        return Excel::download(
            new NilaiExport($this->pembelajaran->id),
            'nilai-' . Str::slug($this->pembelajaran->keterangan) . '-' . date('YmdHi') . '.xlsx'
        );
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Rekap Nilai</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Rekapitulasi nilai siswa: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong></p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran) }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        {{-- TOOLBAR: REKAP NILAI & EXPORT --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/80 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm border border-emerald-500/20 shadow-2xs">
                    <i class="bi bi-award"></i>
                </div>
                <div>
                    <h6 class="text-xs font-bold text-slate-800 dark:text-slate-100 font-display m-0">Rekap Nilai Siswa</h6>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Total {{ $pembelajaran->anggota->count() }} siswa terdaftar</span>
                </div>
            </div>

            <button wire:click="export" class="btn btn-outline-success btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-file-earmark-excel"></i>
                <span>Export Excel</span>
            </button>
        </div>

        {{-- Petunjuk Geser di Layar HP --}}
        <div class="md:hidden text-[11px] text-slate-500 dark:text-slate-400 mb-2 flex items-center justify-between px-1">
            <span class="flex items-center gap-1 font-medium">
                <i class="bi bi-arrows-expand"></i> Geser tabel ke kanan untuk melihat seluruh pertemuan
            </span>
            <span class="text-[10px] text-slate-400">{{ $jurnals->count() }} Pertemuan</span>
        </div>

        <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="table-bordered table-hover mb-0 table align-middle" style="font-size: 0.85rem; min-width: 650px;">
                <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300 text-center">
                    <tr>
                        <th rowspan="2" class="align-middle py-3 px-2 font-bold" width="50">No</th>
                        <th rowspan="2" class="align-middle py-3 px-3 font-bold text-start" width="250">Nama Siswa / Rombel</th>
                        <th colspan="{{ max(1, $jurnals->count()) }}" class="py-2.5 font-bold uppercase tracking-wider text-xs">Materi / Pertemuan</th>
                        <th rowspan="2" class="align-middle py-3 px-2 font-bold" width="100">Nilai Akhir</th>
                    </tr>
                    <tr>
                        @foreach ($jurnals as $j)
                            <th class="font-normal p-2" style="min-width: 100px; max-width: 150px; font-size: 0.75rem;">
                                <div class="text-truncate font-semibold text-slate-800 dark:text-slate-100" title="{{ $j->materi }}">{{ $j->materi }}</div>
                                <div class="text-slate-400 text-[11px] mt-0.5">
                                    {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/y') }}</div>
                            </th>
                        @endforeach
                        @if ($jurnals->isEmpty())
                            <th class="text-slate-400 py-3 font-normal italic">Belum ada data jurnal</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($anggotaList as $index => $anggota)
                        @php $s = $anggota->siswa; @endphp
                        <tr wire:key="rekap-row-{{ $s->id }}">
                            <td class="text-slate-400 text-xs text-center py-2.5">{{ $index + 1 }}</td>
                            <td class="px-3">
                                <div class="font-semibold text-slate-800 dark:text-slate-100" style="font-size: 0.85rem;">
                                    {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                    <span class="text-slate-400 text-xs font-normal">({{ $s->rombel->nama }})</span>
                                </div>
                            </td>
                            @foreach ($jurnals as $j)
                                @php
                                    $val = $nilaiMap[$s->id][$j->id] ?? null;
                                    $dateKey = \Carbon\Carbon::parse($j->tanggal)->format('Y-m-d');
                                    $status = $presensiMap[$s->id][$dateKey] ?? null;
                                @endphp
                                <td class="font-bold text-center p-1.5">
                                    @if ($val !== null)
                                        <span class="badge {{ $val >= 75 ? 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-600 border border-rose-500/30' }} rounded-lg py-1 px-2 font-mono">
                                            {{ $val }}
                                        </span>
                                    @elseif(in_array($status, ['S', 'I']))
                                        @if ($status == 'S')
                                            <span class="badge bg-amber-500/15 text-amber-600 border border-amber-500/30 rounded-lg py-1 px-2 font-semibold">
                                                S
                                            </span>
                                        @else
                                            <span class="badge bg-sky-500/15 text-sky-600 border border-sky-500/30 rounded-lg py-1 px-2 font-semibold">
                                                I
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">-</span>
                                    @endif
                                </td>
                            @endforeach
                            @php
                                $totalScore = 0;
                                $meetingCount = 0;
                                foreach ($jurnals as $j) {
                                    $dateKey = \Carbon\Carbon::parse($j->tanggal)->format('Y-m-d');
                                    $status = $presensiMap[$s->id][$dateKey] ?? 'H'; // Default to Hadir if not set
                                    
                                    // Jika I / S pertemuan bisa diabaikan
                                    if (in_array($status, ['I', 'S'])) {
                                        continue;
                                    }

                                    // Nilai akhir = jumlah seluruh nilai : banyaknya pertemuan jika siswa statusnya H / A
                                    if (in_array($status, ['H', 'A'])) {
                                        $meetingCount++;
                                        $totalScore += ($nilaiMap[$s->id][$j->id] ?? 0);
                                    }
                                }
                                $average = $meetingCount > 0 ? round($totalScore / $meetingCount, 1) : null;
                            @endphp
                            <td class="font-bold text-center p-1.5">
                                <span class="badge {{ ($average ?? 0) >= 75 ? 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-600 border border-rose-500/30' }} rounded-lg py-1 px-2 font-mono">
                                    {{ $average ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        @if ($anggotaList->isEmpty())
            <div class="text-slate-400 py-10 text-center">
                <i class="bi bi-people fs-1 d-block mb-3 text-slate-300"></i>
                Belum ada anggota siswa dalam pembelajaran ini.
            </div>
        @endif
    </div>
</div>
