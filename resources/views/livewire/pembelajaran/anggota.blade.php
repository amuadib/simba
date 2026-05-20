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
            'rombels' => Rombel::orderBy('tingkat')->get(),
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Anggota Pembelajaran</h4>
            <small class="text-muted">Kelola siswa terdaftar untuk:
                <strong>{{ $pembelajaran->keterangan }}</strong></small>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="border-bottom bg-light p-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <table class="table-sm table-borderless mb-0 table">
                            <tr>
                                <td width="120" class="text-muted small">Tahun Ajaran</td>
                                <td class="fw-bold">{{ $pembelajaran->tahunAjaran->nama }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Mata Pelajaran</td>
                                <td class="fw-bold">{{ $pembelajaran->pelajaran->nama }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="{{ route('pembelajaran.index') }}" wire:navigate
                            class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke List
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-4" style="background: rgba(13, 110, 253, 0.02);">
                <div class="row g-4">
                    {{-- SISI KIRI: TERSEDIA --}}
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary fw-bold mb-0"><i class="bi bi-person-plus me-1"></i> Tersedia</h6>
                            <select wire:model.live="rombel_id" class="form-select form-select-sm w-50">
                                <option value="">-- Pilih Kelas --</option>
                                @foreach ($rombels as $r)
                                    <option value="{{ $r->id }}">{{ $r->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="overflow-hidden rounded border bg-white shadow-sm">
                            <select wire:model="selectedAvailable" class="form-select border-0 p-0" multiple
                                size="15" style="border-radius:0;">
                                @foreach ($availableSiswa as $s)
                                    <option value="{{ $s->id }}" class="border-bottom px-3 py-2">
                                        {{ $s->nama }}</option>
                                @endforeach
                            </select>
                            @if ($availableSiswa->isEmpty())
                                <div class="text-muted small px-3 py-5 text-center">
                                    {{ $rombel_id ? 'Semua siswa di kelas ini sudah terdaftar' : 'Pilih kelas untuk melihat daftar siswa' }}
                                </div>
                            @endif
                        </div>
                        <small class="text-muted d-block mt-2">Hold Ctrl/Shift untuk pilih banyak</small>
                    </div>

                    {{-- CENTER: ACTIONS --}}
                    <div class="col-md-2 d-flex flex-column justify-content-center align-items-center gap-3">
                        <button wire:click="addSelected" class="btn btn-primary w-100 py-3" title="Daftarkan Siswa">
                            <i class="bi bi-chevron-right d-none d-md-inline"></i>
                            <i class="bi bi-chevron-down d-md-none"></i>
                        </button>
                        <button wire:click="removeSelected" class="btn btn-outline-danger w-100 py-3"
                            title="Hapus dari Daftar">
                            <i class="bi bi-chevron-left d-none d-md-inline"></i>
                            <i class="bi bi-chevron-up d-md-none"></i>
                        </button>
                    </div>

                    {{-- SISI KANAN: TERDAFTAR --}}
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-success fw-bold mb-0"><i class="bi bi-check-circle me-1"></i> Terdaftar
                                ({{ count($enrolledSiswa) }})</h6>
                        </div>
                        <div class="overflow-hidden rounded border bg-white shadow-sm">
                            <select wire:model="selectedEnrolled" class="form-select border-0 p-0" multiple
                                size="15" style="border-radius:0;">
                                @foreach ($enrolledSiswa as $s)
                                    <option value="{{ $s->id }}" class="border-bottom px-3 py-2">
                                        {{ $s->nama }}</option>
                                @endforeach
                            </select>
                            @if ($enrolledSiswa->isEmpty())
                                <div class="text-muted small px-3 py-5 text-center">
                                    Belum ada siswa terdaftar
                                </div>
                            @endif
                        </div>
                        <small class="text-muted d-block mt-2">Pilih di kanan lalu klik ◀️ untuk mengeluarkan</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
