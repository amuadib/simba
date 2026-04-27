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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Rekap Nilai</h4>
            <small class="text-muted">Pembelajaran: <strong>{{ $pembelajaran->keterangan }}</strong></small>
        </div>

        <div wire:key="breadcrumb-wrapper" class="d-flex align-items-center gap-2" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <button wire:click="export" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i>
                    Export Excel
                </button>
            </div>

            <div class="table-responsive">
                <table class="table-bordered table-hover mb-0 table align-middle">
                    <thead class="table-light small text-center">
                        <tr>
                            <th rowspan="2" class="align-middle" width="50">No</th>
                            <th rowspan="2" class="align-middle" width="250">Nama Siswa / Rombel</th>
                            <th colspan="{{ $jurnals->count() }}" class="p-2">Materi / Pertemuan</th>
                            <th rowspan="2" class="align-middle" width="100">Nilai Akhir</th>
                        </tr>
                        <tr>
                            @foreach ($jurnals as $j)
                                <th class="fw-normal p-1"
                                    style="min-width: 100px; max-width: 150px; font-size: 0.7rem;">
                                    <div class="text-truncate" title="{{ $j->materi }}">{{ $j->materi }}</div>
                                    <div class="text-muted" style="font-size: 0.6rem;">
                                        {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/y') }}</div>
                                </th>
                            @endforeach
                            @if ($jurnals->isEmpty())
                                <th class="text-muted p-4">Belum ada data jurnal</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggotaList as $index => $anggota)
                            @php $s = $anggota->siswa; @endphp
                            <tr wire:key="rekap-row-{{ $s->id }}">
                                <td class="text-muted small text-center">{{ $index + 1 }}</td>
                                <td class="ps-3">
                                    <div class="fw-bold" style="font-size: 0.85rem;">{!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                        ({{ $s->rombel->nama }})
                                    </div>
                                </td>
                                @foreach ($jurnals as $j)
                                    @php
                                        $val = $nilaiMap[$s->id][$j->id] ?? null;
                                        $dateKey = \Carbon\Carbon::parse($j->tanggal)->format('Y-m-d');
                                        $status = $presensiMap[$s->id][$dateKey] ?? null;
                                    @endphp
                                    <td class="fw-bold text-center">
                                        @if ($val !== null)
                                            <span
                                                class="badge {{ $val >= 75 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }} w-100 border p-2">
                                                {{ $val }}
                                            </span>
                                        @elseif(in_array($status, ['S', 'I']))
                                            @if ($status == 'S')
                                                <span class="badge bg-warning bg-opacity-10 text-warning w-100 border p-2">
                                                    S
                                                </span>
                                            @else
                                                <span class="badge bg-primary bg-opacity-10 text-primary w-100 border p-2">
                                                    I
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted opacity-25">-</span>
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
                                <td class="fw-bold text-center">
                                    <span
                                        class="badge {{ ($average ?? 0) >= 75 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }} w-100 border p-2">
                                        {{ $average ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($anggotaList->isEmpty())
                <div class="text-muted p-5 text-center">
                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                    Belum ada anggota siswa dalam pembelajaran ini.
                </div>
            @endif
        </div>
    </div>
</div>
