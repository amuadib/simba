<?php

use function Livewire\Volt\{state, mount, with};
use App\Models\Pembelajaran;
use App\Models\Jurnal;
use App\Models\Nilai;

state([
    'pembelajaran' => null,
]);

mount(function (Pembelajaran $pembelajaran) {
    $this->pembelajaran = $pembelajaran;
});

with(function () {
    $nilai = [];
    $jurnals = Jurnal::where('pembelajaran_id', $this->pembelajaran->id)->orderBy('tanggal')->get();

    $anggotaList = $this->pembelajaran->anggota()->with('siswa.rombel')->get();

    // Get all scores for this pembelajaran
    $allNilai = Nilai::whereIn('jurnal_id', $jurnals->pluck('id'))->get();

    foreach ($allNilai as $n) {
        $nilai[$n->siswa_id][$n->jurnal_id] = $n->nilai;
    }

    return [
        'jurnals' => $jurnals,
        'anggotaList' => $anggotaList->sortBy(fn($a) => $a->siswa->nama),
        'nilaiMap' => $nilai,
    ];
});

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Rekap Nilai</h4>
            <small class="text-muted">Pembelajaran: <strong>{{ $pembelajaran->keterangan }}</strong></small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-bordered table-hover mb-0 table align-middle">
                    <thead class="table-light small text-center">
                        <tr>
                            <th rowspan="2" class="align-middle" width="50">No</th>
                            <th rowspan="2" class="align-middle" width="250">Nama Siswa / Rombel</th>
                            <th colspan="{{ $jurnals->count() }}" class="p-2">Materi / Pertemuan</th>
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
                                    @php $val = $nilaiMap[$s->id][$j->id] ?? null; @endphp
                                    <td class="fw-bold text-center">
                                        @if ($val !== null)
                                            <span
                                                class="badge {{ $val >= 75 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }} w-100 border p-2">
                                                {{ $val }}
                                            </span>
                                        @else
                                            <span class="text-muted opacity-25">-</span>
                                        @endif
                                    </td>
                                @endforeach
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
