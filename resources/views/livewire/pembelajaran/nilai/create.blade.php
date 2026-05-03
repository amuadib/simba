<?php

use App\Models\Pembelajaran;
use App\Models\Jurnal;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\Presensi;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $pembelajaran;
    public $jurnal;
    public $inputNilai = [];
    public $inputPresensi = [];
    public $tanggal = '';

    public function mount(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $this->pembelajaran = $pembelajaran;
        $this->jurnal = $jurnal;
        $this->tanggal = date('Y-m-d', strtotime($jurnal->tanggal));

        $this->inputNilai = Nilai::where('jurnal_id', $jurnal->id)->pluck('nilai', 'siswa_id')->toArray();

        $this->inputPresensi = Presensi::where('pembelajaran_id', $pembelajaran->id)->where('tanggal', $this->tanggal)->pluck('status', 'siswa_id')->toArray();
    }

    public function setNilai($siswaId, $nilai)
    {
        $current = $this->inputNilai[$siswaId] ?? '';
        $next = $current == $nilai ? '' : $nilai;

        $siswa = Siswa::find($siswaId);

        $this->inputNilai[$siswaId] = $next;

        if ($next === '') {
            Nilai::where('siswa_id', $siswaId)->where('jurnal_id', $this->jurnal->id)->delete();
            $this->dispatch('toast', message: 'Nilai siswa ' . $siswa->nama . ' berhasil dihapus', type: 'success');
        } else {
            Nilai::upsert(
                [
                    [
                        'id' => (string) Str::uuid(),
                        'siswa_id' => $siswaId,
                        'jurnal_id' => $this->jurnal->id,
                        'jenis_nilai_id' => 1,
                        'nilai' => $next,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                ['siswa_id', 'jurnal_id', 'jenis_nilai_id'],
                ['nilai', 'updated_at'],
            );
            $this->dispatch('toast', message: 'Nilai siswa ' . $siswa->nama . ' berhasil diupdate', type: 'success');
        }
    }

    public function setPresensi($siswaId, $status)
    {
        $current = $this->inputPresensi[$siswaId] ?? '-';
        $next = $current == $status ? '-' : $status;

        $siswa = Siswa::find($siswaId);

        $this->inputPresensi[$siswaId] = $next;

        if ($next == '-') {
            Presensi::where('siswa_id', $siswaId)->where('pembelajaran_id', $this->pembelajaran->id)->where('tanggal', $this->tanggal)->delete();
            $this->dispatch('toast', message: 'Presensi siswa ' . $siswa->nama . ' berhasil dihapus', type: 'success');
        } else {
            Presensi::upsert(
                [
                    [
                        'id' => (string) Str::uuid(),
                        'siswa_id' => $siswaId,
                        'pembelajaran_id' => $this->pembelajaran->id,
                        'tanggal' => $this->tanggal,
                        'status' => $next,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                ['siswa_id', 'pembelajaran_id', 'tanggal'],
                ['status', 'updated_at'],
            );
            $this->dispatch('toast', message: 'Presensi siswa ' . $siswa->nama . ' berhasil diupdate', type: 'success');
        }
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi & Nilai</h4>
            <small class="text-muted">Jurnal: <strong>{{ $jurnal->materi }}</strong>
                ({{ \Carbon\Carbon::parse($jurnal->tanggal)->locale('id_ID')->isoFormat('DD MMMM YYYY') }})</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-hover mb-0 table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Siswa</th>
                            <th class="text-center" width="220">Presensi</th>
                            <th class="text-center" width="350">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pembelajaran->anggota as $anggota)
                            @php
                                $s = $anggota->siswa;
                                $curP = $inputPresensi[$s->id] ?? '-';
                                $curN = $inputNilai[$s->id] ?? '';
                            @endphp
                            <tr wire:key="row-{{ $s->id }}">
                                <td class="ps-4">
                                    <div class="fw-bold">{!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!} ({{ $s->rombel->nama }})</div>
                                </td>

                                {{-- PRESENSI PRESETS --}}
                                <td>
                                    <div class="btn-group w-100">
                                        @foreach (['H' => 'success', 'I' => 'primary', 'S' => 'warning', 'A' => 'danger'] as $p => $c)
                                            <button wire:key="presensi-{{ $s->id }}-{{ $p }}"
                                                wire:click="setPresensi('{{ $s->id }}', '{{ $p }}')"
                                                class="btn btn-sm btn-outline-{{ $c }} {{ $curP == $p ? 'active fw-bold' : '' }}"
                                                style="width: 25%">
                                                {{ $p }}
                                            </button>
                                        @endforeach
                                    </div>
                                </td>

                                {{-- NILAI PRESETS & INPUT --}}
                                <td>
                                    <div class="d-flex gap-2 {{ $curP !== 'H' ? 'opacity-25' : '' }}">
                                        <div class="btn-group">
                                            @foreach ([70 => 'secondary', 80 => 'info', 90 => 'success', 100 => 'primary'] as $n => $c)
                                                <button wire:key="nilai-{{ $s->id }}-{{ $n }}"
                                                    wire:click="setNilai('{{ $s->id }}', {{ $n }})"
                                                    class="btn btn-sm btn-outline-{{ $c }} {{ $curN == $n ? 'active fw-bold' : '' }}"
                                                    {{ $curP !== 'H' ? 'disabled' : '' }}>
                                                    {{ $n }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <input type="number" wire:model.blur="inputNilai.{{ $s->id }}"
                                            wire:change="setNilai('{{ $s->id }}', $event.target.value)"
                                            class="form-control form-control-sm fw-bold text-center" style="width: 60px"
                                            placeholder="..."
                                            {{ $curP !== 'H' ? 'disabled' : '' }}>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <style>
        .btn-group .btn.active {
            box-shadow: inset 0 3px 5px rgba(0, 0, 0, .125);
            filter: brightness(0.9);
        }

        tr:hover {
            background-color: rgba(13, 110, 253, 0.01) !important;
        }
    </style>
</div>
