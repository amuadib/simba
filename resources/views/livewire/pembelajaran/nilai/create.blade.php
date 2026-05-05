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

    public $editSiswaId;
    public $editSiswaNama;
    public $editSiswaPanggilan;
    public $editSiswaJenisKelamin;

    public function mount(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $this->pembelajaran = $pembelajaran;
        $this->jurnal = $jurnal;
        $this->tanggal = date('Y-m-d', strtotime($jurnal->tanggal));

        $this->inputNilai = Nilai::where('jurnal_id', $jurnal->id)->pluck('nilai', 'siswa_id')->toArray();

        $this->inputPresensi = Presensi::where('pembelajaran_id', $pembelajaran->id)->where('tanggal', $this->tanggal)->pluck('status', 'siswa_id')->toArray();
    }

    public function setNilai($nama, $siswaId, $nilai)
    {
        $current = $this->inputNilai[$siswaId] ?? '';
        $next = $current == $nilai ? '' : $nilai;

        $this->inputNilai[$siswaId] = $next;

        if ($next === '') {
            Nilai::where('siswa_id', $siswaId)->where('jurnal_id', $this->jurnal->id)->delete();
            $this->dispatch('toast', message: 'Nilai siswa ' . $nama . ' berhasil dihapus', type: 'success');
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
            $this->dispatch('toast', message: 'Nilai siswa ' . $nama . ' berhasil diupdate', type: 'success');
        }
    }

    public function setPresensi($nama, $siswaId, $status)
    {
        $current = $this->inputPresensi[$siswaId] ?? '-';
        $next = $current == $status ? '-' : $status;

        $this->inputPresensi[$siswaId] = $next;

        if ($next == '-') {
            Presensi::where('siswa_id', $siswaId)->where('pembelajaran_id', $this->pembelajaran->id)->where('tanggal', $this->tanggal)->delete();
            $this->dispatch('toast', message: 'Presensi siswa ' . $nama . ' berhasil dihapus', type: 'success');
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
            $this->dispatch('toast', message: 'Presensi siswa ' . $nama . ' berhasil diupdate', type: 'success');
        }
    }

    public function editDataSantri($siswaId)
    {
        $siswa = Siswa::find($siswaId);
        if ($siswa) {
            $this->editSiswaId = $siswa->id;
            $this->editSiswaNama = $siswa->nama;
            $this->editSiswaPanggilan = $siswa->panggilan;
            $this->editSiswaJenisKelamin = $siswa->jenis_kelamin;
            $this->dispatch('open-modal', id: 'modalEditSantri');
        }
    }

    public function updateDataSantri()
    {
        $siswa = Siswa::find($this->editSiswaId);
        if ($siswa) {
            $siswa->update([
                'nama' => $this->editSiswaNama,
                'panggilan' => $this->editSiswaPanggilan,
                'jenis_kelamin' => $this->editSiswaJenisKelamin,
            ]);
            $this->pembelajaran->load('anggota.siswa');
            $this->dispatch('close-modal', id: 'modalEditSantri');
            $this->dispatch('toast', message: 'Data santri berhasil diupdate', type: 'success');
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
                                    <div class="fw-bold d-flex align-items-center justify-content-between pe-3">
                                        <div>
                                            {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!} <span class="text-muted fw-normal">({{ $s->rombel->nama }})</span>
                                        </div>
                                        <button wire:click="editDataSantri('{{ $s->id }}')" class="btn btn-sm btn-link text-secondary p-0" title="Edit Data Santri">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                </td>

                                {{-- PRESENSI PRESETS --}}
                                <td>
                                    <div class="btn-group w-100">
                                        @foreach (['H' => 'success', 'I' => 'primary', 'S' => 'warning', 'A' => 'danger'] as $p => $c)
                                            <button wire:key="presensi-{{ $s->id }}-{{ $p }}"
                                                wire:click="setPresensi('{{ addslashes($s->nama) }}', '{{ $s->id }}', '{{ $p }}')"
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
                                                    wire:click="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', {{ $n }})"
                                                    class="btn btn-sm btn-outline-{{ $c }} {{ $curN == $n ? 'active fw-bold' : '' }}"
                                                    {{ $curP !== 'H' ? 'disabled' : '' }}>
                                                    {{ $n }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <input type="number" wire:model.blur="inputNilai.{{ $s->id }}"
                                            wire:change="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', $event.target.value)"
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

    {{-- MODAL EDIT SANTRI --}}
    <div wire:ignore.self class="modal fade" id="modalEditSantri" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit="updateDataSantri">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Data Santri</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" class="form-control" wire:model="editSiswaNama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Panggilan</label>
                            <input type="text" class="form-control" wire:model="editSiswaPanggilan">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold d-block">Jenis Kelamin</label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input wire:model="editSiswaJenisKelamin" class="form-check-input" type="radio" value="L" id="editJkL" required>
                                    <label class="form-check-label" for="editJkL">Laki-laki (L)</label>
                                </div>
                                <div class="form-check">
                                    <input wire:model="editSiswaJenisKelamin" class="form-check-input" type="radio" value="P" id="editJkP" required>
                                    <label class="form-check-label" for="editJkP">Perempuan (P)</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> SIMPAN PERUBAHAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @script
    <script>
        $wire.on('close-modal', ({id}) => {
            bootstrap.Modal.getInstance(document.getElementById(id))?.hide();
        });
        $wire.on('open-modal', ({id}) => {
            new bootstrap.Modal(document.getElementById(id)).show();
        });
    </script>
    @endscript
</div>
