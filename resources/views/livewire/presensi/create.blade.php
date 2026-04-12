<?php

use function Livewire\Volt\{state, mount, updated};
use App\Models\Pembelajaran;
use App\Models\Presensi;
use Illuminate\Support\Str;

state([
    'pembelajaran' => null,
    'tanggal' => date('Y-m-d'),
    'listSiswa' => [],
    'inputData' => [], // [siswa_id => ['status' => 'A', 'keterangan' => '']]
]);

mount(function (Pembelajaran $pembelajaran) {
    if (request()->tanggal) {
        $this->tanggal = request()->tanggal;
    }
    $this->pembelajaran = $pembelajaran;
    $this->loadPresensi();
});

updated([
    'tanggal' => function () {
        $this->loadPresensi();
    },
]);

$loadPresensi = function () {
    $presensi = Presensi::where('pembelajaran_id', $this->pembelajaran->id)->where('tanggal', $this->tanggal)->get()->keyBy('siswa_id');

    $this->listSiswa = $this->pembelajaran
        ->anggota()
        ->with('siswa')
        ->get()
        ->map(function ($anggota) {
            return $anggota->siswa;
        })
        ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
        ->values();

    $this->inputData = [];
    foreach ($this->listSiswa as $siswa) {
        $this->inputData[$siswa->id] = [
            'status' => $presensi[$siswa->id]->status ?? 'A',
            'keterangan' => $presensi[$siswa->id]->keterangan ?? '',
        ];
    }
};

$submit = function () {
    $this->validate([
        'tanggal' => 'required|date',
        'inputData' => 'required|array',
    ]);

    $rows = [];
    foreach ($this->inputData as $siswa_id => $item) {
        $rows[] = [
            'id' => (string) Str::uuid(),
            'pembelajaran_id' => $this->pembelajaran->id,
            'siswa_id' => $siswa_id,
            'tanggal' => $this->tanggal,
            'status' => $item['status'],
            'keterangan' => $item['keterangan'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    if (!empty($rows)) {
        Presensi::upsert($rows, ['siswa_id', 'pembelajaran_id', 'tanggal'], ['status', 'keterangan', 'updated_at']);

        session()->flash('success', 'Presensi berhasil disimpan.');
        return redirect()->route('pembelajaran.index');
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi</h4>
            <small class="text-muted">Input Presensi {{ $pembelajaran->keterangan }}</small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-2 mb-4">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tanggal</label>
                    <input type="date" wire:model.live="tanggal" class="form-control" required>
                </div>
            </div>

            <form wire:submit="submit">
                <div class="table-responsive">
                    <table class="table-hover table border align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="300">Nama Siswa</th>
                                <th class="text-center">Status</th>
                                <th class="d-none d-md-table-cell">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($listSiswa as $siswa)
                                <tr wire:key="siswa-{{ $siswa->id }}">
                                    <td>
                                        <div class="fw-bold">{{ $siswa->nama }}</div>
                                        <small class="text-muted">{{ $siswa->panggilan }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            @foreach (['H', 'I', 'S', 'A'] as $stat)
                                                <div class="form-check form-check-inline m-0">
                                                    <input type="radio"
                                                        wire:model="inputData.{{ $siswa->id }}.status"
                                                        id="status-{{ $siswa->id }}-{{ $stat }}"
                                                        value="{{ $stat }}" class="form-check-input">
                                                    <label class="form-check-label small"
                                                        for="status-{{ $siswa->id }}-{{ $stat }}">{{ $stat }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <input type="text" wire:model="inputData.{{ $siswa->id }}.keterangan"
                                            class="form-control form-control-sm" placeholder="...">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted py-4 text-center">Tidak ada anggota siswa dalam
                                        pembelajaran ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (!empty($listSiswa))
                    <div class="d-flex justify-content-end mt-4 gap-2">
                        <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-secondary">KEMBALI</a>
                        <button type="submit" class="btn btn-success px-4" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> SIMPAN PRESENSI
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
