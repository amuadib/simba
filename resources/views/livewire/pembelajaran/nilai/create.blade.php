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
    public $sort = 'nama';

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
            $this->editSiswaPanggilan = $siswa->panggilan ?? $siswa->nama;
            $this->editSiswaJenisKelamin = $siswa->jenis_kelamin;
        }
    }

    public function closeEditSantriModal()
    {
        $this->editSiswaId = null;
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
            $this->editSiswaId = null;
            $this->dispatch('toast', message: 'Data santri berhasil diupdate', type: 'success');
        }
    }

    public function toggleSort()
    {
        $this->sort = $this->sort == 'nama' ? 'panggilan' : 'nama';
    }
    public function with()
    {
        if ($this->sort == 'nama') {
            return [
                'anggota' => $this->pembelajaran->anggota,
            ];
        } elseif ($this->sort == 'panggilan') {
            return [
                'anggota' => $this->pembelajaran->anggotaSortPanggilan,
            ];
        }
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Presensi & Nilai</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Jurnal: <strong class="text-slate-700 dark:text-slate-200">{{ $jurnal->materi }}</strong> ({{ \Carbon\Carbon::parse($jurnal->tanggal)->locale('id_ID')->isoFormat('DD MMMM YYYY') }})</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran->id) }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 overflow-hidden shadow-sm">
            <table class="table-hover mb-0 table align-middle" style="font-size: 0.85rem;">
                <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                    <tr>
                        <th class="py-3 px-4">
                            <div class="d-flex align-items-center">
                                <span>Nama Siswa</span>
                                <button wire:click="toggleSort()" class="btn btn-xs rounded-lg px-2 py-0.5 ms-2 font-bold font-display {{ $sort == 'nama' ? 'btn-primary' : 'btn-success' }}" title="Ubah urutan (Nama Lengkap / Panggilan)">
                                    @if ($sort == 'nama')
                                        L
                                    @elseif ($sort == 'panggilan')
                                        P
                                    @endif
                                </button>
                            </div>
                        </th>
                        <th class="text-center py-3 px-4" width="220">Presensi</th>
                        <th class="text-center py-3 px-4" width="350">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($anggota as $a)
                        @php
                            $s = $a->siswa;
                            $curP = $inputPresensi[$s->id] ?? '-';
                            $curN = $inputNilai[$s->id] ?? '';
                        @endphp
                        <tr wire:key="row-{{ $s->id }}">
                            <td class="px-4">
                                <div class="font-semibold d-flex align-items-center justify-content-between pe-3 text-slate-800 dark:text-slate-100">
                                    <div>
                                        {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!} <span class="text-slate-400 text-xs font-normal">({{ $s->rombel->nama }})</span>
                                    </div>
                                    <button wire:click="editDataSantri('{{ $s->id }}')" class="btn btn-sm btn-link text-amber-500 p-0" title="Edit Data Santri">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>

                            {{-- PRESENSI PRESETS --}}
                            <td class="px-4 text-center">
                                <div class="btn-group w-100 rounded-xl overflow-hidden shadow-2xs">
                                    @foreach (['H' => 'success', 'I' => 'primary', 'S' => 'warning', 'A' => 'danger'] as $p => $c)
                                        <button wire:key="presensi-{{ $s->id }}-{{ $p }}"
                                            wire:click="setPresensi('{{ addslashes($s->nama) }}', '{{ $s->id }}', '{{ $p }}')"
                                            class="btn btn-sm btn-outline-{{ $c }} {{ $curP == $p ? 'active font-bold' : '' }}"
                                            style="width: 25%">
                                            {{ $p }}
                                        </button>
                                    @endforeach
                                </div>
                            </td>

                            {{-- NILAI PRESETS & INPUT --}}
                            <td class="px-4">
                                <div class="d-flex items-center gap-2 {{ $curP !== 'H' ? 'opacity-25' : '' }}">
                                    <div class="btn-group rounded-xl overflow-hidden shadow-2xs">
                                        @foreach ([70 => 'secondary', 80 => 'info', 90 => 'success', 100 => 'primary'] as $n => $c)
                                            <button wire:key="nilai-{{ $s->id }}-{{ $n }}"
                                                wire:click="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', {{ $n }})"
                                                class="btn btn-sm btn-outline-{{ $c }} {{ $curN == $n ? 'active font-bold' : '' }}"
                                                {{ $curP !== 'H' ? 'disabled' : '' }}>
                                                {{ $n }}
                                            </button>
                                        @endforeach
                                    </div>
                                    <input type="number" wire:model.blur="inputNilai.{{ $s->id }}"
                                        wire:change="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', $event.target.value)"
                                        class="form-control form-control-sm rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-bold text-center text-xs py-1" style="width: 65px"
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
    @if ($editSiswaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <form wire:submit="updateDataSantri">
                    <div class="bg-gradient-to-r from-sky-600 to-cyan-600 text-white p-4 flex items-center justify-between">
                        <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm">
                            <i class="bi bi-pencil-square text-cyan-200"></i> Edit Data Santri
                        </h5>
                        <button type="button" wire:click="closeEditSantriModal" class="text-white hover:text-slate-200">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Nama Lengkap</label>
                            <input type="text" class="form-control form-control-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl font-semibold" wire:model="editSiswaNama" required>
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">Nama Panggilan</label>
                            <input type="text" class="form-control form-control-sm bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl" wire:model="editSiswaPanggilan">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300 d-block">Jenis Kelamin</label>
                            <div class="flex gap-4 pt-1">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    <input wire:model="editSiswaJenisKelamin" class="form-check-input text-sky-600" type="radio" value="L" id="editJkL" required>
                                    <span>Laki-laki (L)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    <input wire:model="editSiswaJenisKelamin" class="form-check-input text-pink-600" type="radio" value="P" id="editJkP" required>
                                    <span>Perempuan (P)</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                            <button type="button" wire:click="closeEditSantriModal" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold">
                                Batal
                            </button>
                            <button type="submit" class="btn-logo-gradient px-5 py-2 rounded-xl text-xs font-bold shadow-md flex items-center gap-1.5" wire:loading.attr="disabled">
                                <i class="bi bi-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
