<?php

use App\Models\Pembelajaran;
use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\AnggotaPembelajaran;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $pembelajaran;
    public $rombel_id = '';
    public $availableIds = [];
    public $selectedAvailable = [];
    public $selectedEnrolled = [];

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->pembelajaran = $pembelajaran;
    }

    public function addSelected()
    {
        if (empty($this->selectedAvailable)) {
            return;
        }

        $data = [];
        foreach ($this->selectedAvailable as $id) {
            $data[] = [
                'pembelajaran_id' => $this->pembelajaran->id,
                'siswa_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AnggotaPembelajaran::insert($data);
        $this->reset(['selectedAvailable']);
    }

    public function removeSelected()
    {
        if (empty($this->selectedEnrolled)) {
            return;
        }

        AnggotaPembelajaran::where('pembelajaran_id', $this->pembelajaran->id)
            ->whereIn('siswa_id', $this->selectedEnrolled)
            ->delete();

        $this->reset(['selectedEnrolled']);
    }

    public function with()
    {
        $enrolledIds = $this->pembelajaran->anggota()->pluck('siswa_id')->toArray();

        return [
            'rombels' => Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))->orderBy('tingkat')->get(),
            'availableSiswa' => Siswa::where('rombel_id', $this->rombel_id)
            ->where('status', 1)
                ->whereNotIn('id', $enrolledIds)
                ->orderBy('nama')
                ->get(),
            'enrolledSiswa' => Siswa::whereIn('id', $enrolledIds)
                ->orderBy('nama')
                ->get(),
        ];
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Anggota Pembelajaran</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola siswa terdaftar untuk: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong></p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali ke Daftar</span>
            </a>
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Tahun Ajaran:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ $pembelajaran->tahunAjaran->nama }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Mata Pelajaran:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ $pembelajaran->pelajaran->nama }}</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- SISI KIRI: TERSEDIA --}}
            <div class="col-md-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h6 class="text-sky-600 dark:text-cyan-400 font-bold mb-0 text-xs flex items-center gap-1.5 font-display">
                        <i class="bi bi-person-plus-fill"></i> Siswa Tersedia
                    </h6>
                    <select wire:model.live="rombel_id" class="form-select form-select-sm rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-1.5 shadow-xs font-medium w-auto">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($rombels as $r)
                            <option value="{{ $r->id }}">{{ $r->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs">
                    <select wire:model="selectedAvailable" class="form-select border-0 p-0 text-xs" multiple size="15" style="border-radius:0;">
                        @foreach ($availableSiswa as $s)
                            <option value="{{ $s->id }}" class="border-b border-slate-100 dark:border-slate-800 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-sky-50 dark:hover:bg-slate-800">
                                {{ $s->nama }}
                            </option>
                        @endforeach
                    </select>
                    @if ($availableSiswa->isEmpty())
                        <div class="text-slate-400 text-xs px-3 py-10 text-center">
                            {{ $rombel_id ? 'Semua siswa di kelas ini sudah terdaftar' : 'Pilih kelas untuk melihat daftar siswa' }}
                        </div>
                    @endif
                </div>
                <small class="text-slate-400 text-xs d-block mt-2">Hold Ctrl / Shift untuk memilih beberapa siswa</small>
            </div>

            {{-- CENTER: ACTIONS --}}
            <div class="col-md-2 flex flex-col justify-center items-center gap-3">
                <button wire:click="addSelected" class="btn-logo-gradient w-100 py-3 rounded-2xl flex items-center justify-center text-sm shadow-md" title="Daftarkan Siswa">
                    <i class="bi bi-chevron-right d-none d-md-inline"></i>
                    <i class="bi bi-chevron-down d-md-none"></i>
                </button>
                <button wire:click="removeSelected" class="btn btn-outline-danger w-100 py-3 rounded-2xl flex items-center justify-center text-sm shadow-xs" title="Hapus dari Daftar">
                    <i class="bi bi-chevron-left d-none d-md-inline"></i>
                    <i class="bi bi-chevron-up d-md-none"></i>
                </button>
            </div>

            {{-- SISI KANAN: TERDAFTAR --}}
            <div class="col-md-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h6 class="text-emerald-600 dark:text-emerald-400 font-bold mb-0 text-xs flex items-center gap-1.5 font-display">
                        <i class="bi bi-check-circle-fill"></i> Terdaftar ({{ count($enrolledSiswa) }})
                    </h6>
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs">
                    <select wire:model="selectedEnrolled" class="form-select border-0 p-0 text-xs" multiple size="15" style="border-radius:0;">
                        @foreach ($enrolledSiswa as $s)
                            <option value="{{ $s->id }}" class="border-b border-slate-100 dark:border-slate-800 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-slate-800">
                                {{ $s->nama }}
                            </option>
                        @endforeach
                    </select>
                    @if ($enrolledSiswa->isEmpty())
                        <div class="text-slate-400 text-xs px-3 py-10 text-center">
                            Belum ada siswa terdaftar
                        </div>
                    @endif
                </div>
                <small class="text-slate-400 text-xs d-block mt-2">Pilih di kanan lalu klik ◀️ untuk mengeluarkan</small>
            </div>
        </div>
    </div>
</div>
