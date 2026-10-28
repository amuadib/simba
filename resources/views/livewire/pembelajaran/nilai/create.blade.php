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
    public $presensiStatus = []; // Read-only map of attendance status for this meeting date
    public $tanggal = '';
    public $sort = 'nama';
    public $search = '';

    public $editSiswaId;
    public $editSiswaNama;
    public $editSiswaPanggilan;
    public $editSiswaJenisKelamin;

    public function mount(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $this->pembelajaran = $pembelajaran;
        $this->jurnal = $jurnal;
        $this->tanggal = date('Y-m-d', strtotime($jurnal->tanggal));

        $this->loadData();
    }

    public function loadData()
    {
        $this->inputNilai = Nilai::where('jurnal_id', $this->jurnal->id)
            ->pluck('nilai', 'siswa_id')
            ->toArray();

        $this->presensiStatus = Presensi::where('pembelajaran_id', $this->pembelajaran->id)
            ->where('tanggal', $this->tanggal)
            ->pluck('status', 'siswa_id')
            ->toArray();
    }

    public function setNilai($nama, $siswaId, $nilai)
    {
        $current = $this->inputNilai[$siswaId] ?? '';
        $next = ($current == $nilai && $nilai !== '') ? '' : $nilai;

        $this->inputNilai[$siswaId] = $next;

        if ($next === '' || $next === null) {
            Nilai::where('siswa_id', $siswaId)->where('jurnal_id', $this->jurnal->id)->delete();
            $this->dispatch('toast', message: 'Nilai siswa ' . $nama . ' dihapus', type: 'info');
        } else {
            Nilai::updateOrCreate(
                [
                    'siswa_id' => $siswaId,
                    'jurnal_id' => $this->jurnal->id,
                    'jenis_nilai_id' => 1,
                ],
                [
                    'nilai' => $next,
                ]
            );
            $this->dispatch('toast', message: 'Nilai ' . $nama . ' (' . $next . ') disimpan', type: 'success');
        }
    }

    public function setBulkNilai($nilai, $hanyaHadir = false)
    {
        $students = $this->getFilteredStudentsProperty();
        if ($students->isEmpty()) {
            return;
        }

        $count = 0;
        foreach ($students as $a) {
            $s = $a->siswa ?? $a;
            $statusPresensi = $this->presensiStatus[$s->id] ?? null;

            if ($hanyaHadir && $statusPresensi !== 'H') {
                continue;
            }

            $this->inputNilai[$s->id] = $nilai;

            if ($nilai === '' || $nilai === null) {
                Nilai::where('siswa_id', $s->id)->where('jurnal_id', $this->jurnal->id)->delete();
            } else {
                Nilai::updateOrCreate(
                    [
                        'siswa_id' => $s->id,
                        'jurnal_id' => $this->jurnal->id,
                        'jenis_nilai_id' => 1,
                    ],
                    [
                        'nilai' => $nilai,
                    ]
                );
            }
            $count++;
        }

        if ($nilai === '' || $nilai === null) {
            $this->dispatch('toast', message: 'Semua nilai berhasil dikosongkan (' . $count . ' siswa)', type: 'info');
        } else {
            $info = $hanyaHadir ? ' (Siswa Hadir Saja: ' . $count . ')' : ' (' . $count . ' siswa)';
            $this->dispatch('toast', message: 'Nilai ' . $nilai . ' diterapkan' . $info, type: 'success');
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
            $this->dispatch('toast', message: 'Data santri berhasil diperbarui', type: 'success');
        }
    }

    public function toggleSort()
    {
        $this->sort = $this->sort == 'nama' ? 'panggilan' : 'nama';
    }

    public function getFilteredStudentsProperty()
    {
        $query = $this->sort == 'nama'
            ? $this->pembelajaran->anggota()->with('siswa.rombel')->get()
            : $this->pembelajaran->anggotaSortPanggilan()->with('siswa.rombel')->get();

        if (!empty(trim($this->search))) {
            $keyword = strtolower(trim($this->search));
            $query = $query->filter(function ($a) use ($keyword) {
                $s = $a->siswa;
                if (!$s) return false;
                return str_contains(strtolower($s->nama ?? ''), $keyword)
                    || str_contains(strtolower($s->panggilan ?? ''), $keyword)
                    || str_contains(strtolower($s->nis ?? ''), $keyword);
            });
        }

        return $query->values();
    }

    public function with()
    {
        $filtered = $this->filteredStudents;
        $allAnggota = $this->pembelajaran->anggota;
        $totalSiswa = $allAnggota->count();

        $scores = array_filter($this->inputNilai, fn($val) => is_numeric($val) && $val !== '');
        $sudahDinilai = count($scores);
        $belumDinilai = max(0, $totalSiswa - $sudahDinilai);
        $avgScore = $sudahDinilai > 0 ? round(array_sum($scores) / $sudahDinilai, 1) : 0;
        $maxScore = $sudahDinilai > 0 ? max($scores) : 0;
        $minScore = $sudahDinilai > 0 ? min($scores) : 0;

        return [
            'anggota' => $filtered,
            'totalSiswa' => $totalSiswa,
            'sudahDinilai' => $sudahDinilai,
            'belumDinilai' => $belumDinilai,
            'avgScore' => $avgScore,
            'maxScore' => $maxScore,
            'minScore' => $minScore,
        ];
    }
};

?>

<div>
    {{-- TOP BAR --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-cyan-300 border border-sky-200 dark:border-sky-800">
                    Penilaian Pertemuan
                </span>
                <span class="text-xs text-slate-400">
                    {{ \Carbon\Carbon::parse($jurnal->tanggal)->locale('id_ID')->isoFormat('dddd, DD MMMM YYYY') }}
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0 mt-1">
                Input Nilai Jurnal
            </h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                Materi: <strong class="text-slate-700 dark:text-slate-200">{{ $jurnal->materi }}</strong> • Kelas: <strong>{{ $pembelajaran->keterangan }}</strong>
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- TOMBOL CEPAT KE PRESENSI PERTEMUAN INI --}}
            <a href="{{ route('pembelajaran.presensi.create', $pembelajaran->id) }}?tanggal={{ $tanggal }}" wire:navigate
               class="btn btn-outline-success btn-sm rounded-xl px-3.5 py-2 text-xs font-bold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-calendar-check"></i>
                <span>Presensi Pertemuan</span>
            </a>

            <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran->id) }}" wire:navigate
               class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali ke Jurnal</span>
            </a>

            <div wire:key="breadcrumb-wrapper" wire:ignore>
                <x-breadcrumb />
            </div>
        </div>
    </div>

    {{-- STATS PENILAIAN JURNAL --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {{-- TOTAL SISWA --}}
        <div class="glass-panel p-3.5 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-cyan-400 flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Siswa</div>
                <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 font-display">{{ $totalSiswa }}</div>
            </div>
        </div>

        {{-- SUDAH DINILAI --}}
        <div class="glass-panel p-3.5 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Sudah Dinilai</div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-display">
                    {{ $sudahDinilai }} <span class="text-xs font-normal text-slate-400">/ {{ $totalSiswa }}</span>
                </div>
            </div>
        </div>

        {{-- RATA-RATA NILAI --}}
        <div class="glass-panel p-3.5 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-bar-chart-fill"></i>
            </div>
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rata-Rata Kelas</div>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400 font-display">{{ $avgScore }}</div>
            </div>
        </div>

        {{-- TERTINGGI & TERENDAH --}}
        <div class="glass-panel p-3.5 rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-award-fill"></i>
            </div>
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Tertinggi / Terendah</div>
                <div class="text-lg font-extrabold text-slate-800 dark:text-slate-100 font-display">
                    <span class="text-emerald-600">{{ $maxScore }}</span> / <span class="text-rose-500">{{ $minScore }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- TOOLBAR: PENCARIAN & BULK ACTIONS NILAI CEPAT --}}
    <div class="glass-panel rounded-3xl p-4 md:p-5 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            {{-- SEARCH BAR --}}
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="bi bi-search text-xs"></i>
                </div>
                <input type="text"
                       wire:model.live.debounce.200ms="search"
                       class="form-control form-control-sm rounded-xl pl-9 pr-3 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-2xs"
                       placeholder="Cari nama atau NIS siswa...">
                @if ($search)
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="bi bi-x-circle-fill text-xs"></i>
                    </button>
                @endif
            </div>

            {{-- BULK ACTIONS --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                    <i class="bi bi-lightning-charge-fill text-amber-500"></i> Isi Cepat:
                </span>
                <div class="btn-group btn-group-sm rounded-xl overflow-hidden shadow-2xs">
                    @foreach ([75, 80, 85, 90, 100] as $presetScore)
                        <button wire:click="setBulkNilai({{ $presetScore }})"
                                class="btn btn-outline-primary btn-sm text-xs font-bold px-2.5 py-1">
                            {{ $presetScore }}
                        </button>
                    @endforeach
                </div>

                <div class="btn-group btn-group-sm rounded-xl overflow-hidden shadow-2xs">
                    <button wire:click="setBulkNilai(85, true)"
                            class="btn btn-outline-success btn-sm text-xs font-semibold px-2.5 py-1"
                            title="Set nilai 85 hanya untuk siswa yang hadir">
                        Hadir Saja (85)
                    </button>
                    <button wire:click="setBulkNilai('')"
                            wire:confirm="Yakin ingin mengosongkan semua nilai pada jurnal ini?"
                            class="btn btn-outline-danger btn-sm text-xs font-semibold px-2.5 py-1"
                            title="Kosongkan nilai semua siswa">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- DAFTAR SISWA & PENILAIAN --}}
    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="table-responsive rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 overflow-hidden shadow-sm">
            <table class="table-hover mb-0 table align-middle" style="font-size: 0.85rem;">
                <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                    <tr>
                        <th class="py-3 px-4" width="60">No</th>
                        <th class="py-3 px-4">
                            <div class="d-flex align-items-center">
                                <span>Nama Siswa</span>
                                <button wire:click="toggleSort()" class="btn btn-xs rounded-lg px-2 py-0.5 ms-2 font-bold font-display {{ $sort == 'nama' ? 'btn-primary' : 'btn-success' }}" title="Ubah urutan (Nama Lengkap / Panggilan)">
                                    @if ($sort == 'nama')
                                        Lengkap
                                    @elseif ($sort == 'panggilan')
                                        Panggilan
                                    @endif
                                </button>
                            </div>
                        </th>
                        <th class="text-center py-3 px-4" width="180">Status Presensi</th>
                        <th class="text-center py-3 px-4" width="380">Nilai Siswa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggota as $idx => $a)
                        @php
                            $s = $a->siswa;
                            $curP = $presensiStatus[$s->id] ?? null;
                            $curN = $inputNilai[$s->id] ?? '';
                        @endphp
                        <tr wire:key="row-nilai-{{ $s->id }}">
                            <td class="px-4 text-center font-bold text-slate-400">
                                {{ $idx + 1 }}
                            </td>
                            <td class="px-4">
                                <div class="font-semibold d-flex align-items-center justify-content-between pe-3 text-slate-800 dark:text-slate-100">
                                    <div>
                                        {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                        <div class="text-[11px] text-slate-400 font-normal">
                                            NIS: {{ $s->nis ?? $s->nisn ?? '-' }} ({{ $s->rombel->nama ?? '-' }})
                                        </div>
                                    </div>
                                    <button wire:click="editDataSantri('{{ $s->id }}')" class="btn btn-sm btn-link text-amber-500 p-0" title="Edit Data Santri">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>

                            {{-- STATUS PRESENSI (INFORMATIF, MEMISAHKAN PROSES INPUT) --}}
                            <td class="px-4 text-center">
                                @if ($curP === 'H')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-2xs">
                                        <i class="bi bi-check-circle-fill"></i> Hadir
                                    </span>
                                @elseif ($curP === 'I')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 shadow-2xs">
                                        <i class="bi bi-info-circle-fill"></i> Izin
                                    </span>
                                @elseif ($curP === 'S')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-300 dark:border-sky-700 shadow-2xs">
                                        <i class="bi bi-heart-pulse-fill"></i> Sakit
                                    </span>
                                @elseif ($curP === 'A')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700 shadow-2xs">
                                        <i class="bi bi-x-circle-fill"></i> Alpa
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-semibold bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                        <i class="bi bi-dash"></i> Belum Tercatat
                                    </span>
                                @endif
                            </td>

                            {{-- NILAI INPUT & PRESETS --}}
                            <td class="px-4">
                                <div class="d-flex items-center justify-center gap-2">
                                    <div class="btn-group rounded-xl overflow-hidden shadow-2xs">
                                        @foreach ([70 => 'secondary', 80 => 'info', 85 => 'primary', 90 => 'success', 100 => 'warning'] as $n => $c)
                                            <button wire:key="nilai-{{ $s->id }}-{{ $n }}"
                                                wire:click="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', {{ $n }})"
                                                class="btn btn-sm btn-outline-{{ $c }} {{ $curN == $n ? 'active font-black' : '' }}">
                                                {{ $n }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <input type="number"
                                        wire:model.blur="inputNilai.{{ $s->id }}"
                                        wire:change="setNilai('{{ addslashes($s->nama) }}', '{{ $s->id }}', $event.target.value)"
                                        class="form-control form-control-sm rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-extrabold text-center text-xs py-1.5 shadow-2xs"
                                        style="width: 75px"
                                        placeholder="0-100">
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-slate-400 py-8">
                                <i class="bi bi-person-x text-2xl d-block mb-1 text-slate-300"></i>
                                Tidak ada data siswa yang cocok dengan kriteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL EDIT SANTRI --}}
    @if ($editSiswaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-cyan-500/20 shadow-2xl bg-white dark:bg-slate-900 overflow-hidden">
                <form wire:submit="updateDataSantri">
                    <div class="bg-gradient-to-r from-sky-600 to-cyan-600 text-white p-4 flex items-center justify-between">
                        <h5 class="font-display font-bold text-white flex items-center gap-2 text-sm m-0">
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
