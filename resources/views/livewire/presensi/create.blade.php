<?php

use App\Models\Pembelajaran;
use App\Models\Presensi;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $pembelajaran;
    public $tanggal;
    public $listSiswa = [];
    public $inputData = []; // [siswa_id => ['status' => 'A', 'keterangan' => '']]

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->tanggal = request()->tanggal ?? date('Y-m-d');
        $this->pembelajaran = $pembelajaran;
        $this->loadPresensi();
    }

    public function updatedTanggal()
    {
        $this->loadPresensi();
    }

    public function loadPresensi()
    {
        $presensi = Presensi::where('pembelajaran_id', $this->pembelajaran->id)
            ->where('tanggal', $this->tanggal)
            ->get()
            ->keyBy('siswa_id');

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
    }

    public function submit()
    {
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
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Presensi Kelas</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Input presensi kehadiran siswa: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong></p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
            <div class="row g-2.5 items-center">
                <div class="col-md-3">
                    <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tanggal Pertemuan</label>
                    <input type="date" wire:model.live="tanggal" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs" required>
                </div>
            </div>
        </div>

        <form wire:submit="submit">
            <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 overflow-hidden shadow-sm">
                <table class="table-hover mb-0 table align-middle" style="font-size: 0.85rem;">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th class="py-3 px-4" width="300">Nama Siswa</th>
                            <th class="py-3 px-4 text-center" width="220">Status</th>
                            <th class="py-3 px-4 d-none d-md-table-cell">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listSiswa as $siswa)
                            <tr wire:key="siswa-{{ $siswa->id }}">
                                <td class="px-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $siswa->nama }}</div>
                                    <small class="text-slate-400 text-xs">{{ $siswa->panggilan }}</small>
                                </td>
                                <td class="px-4 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        @foreach (['H' => 'success', 'I' => 'primary', 'S' => 'warning', 'A' => 'danger'] as $stat => $c)
                                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300 m-0">
                                                <input type="radio"
                                                    wire:model="inputData.{{ $siswa->id }}.status"
                                                    id="status-{{ $siswa->id }}-{{ $stat }}"
                                                    value="{{ $stat }}" class="form-check-input m-0">
                                                <span>{{ $stat }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 d-none d-md-table-cell">
                                    <input type="text" wire:model="inputData.{{ $siswa->id }}.keterangan"
                                        class="form-control form-control-sm rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-1.5 shadow-xs" placeholder="Keterangan (opsional)...">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-slate-400 py-6 text-center">Tidak ada anggota siswa dalam pembelajaran ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (!empty($listSiswa))
                <div class="flex items-center justify-end mt-4 gap-2">
                    <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-4 py-2.5 text-xs font-semibold">KEMBALI</a>
                    <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold shadow-md" wire:loading.attr="disabled">
                        <i class="bi bi-save"></i>
                        <span>SIMPAN PRESENSI</span>
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
