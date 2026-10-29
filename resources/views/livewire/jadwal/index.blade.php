@section('title', 'Jadwal Pelajaran '. session('tahun_ajaran_nama'))
<?php

use App\Models\Jadwal;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public $filter_user_id = '';

    public function with()
    {
        $query = Jadwal::with(['pembelajaran.pelajaran', 'pembelajaran.tahunAjaran', 'user'])
            ->whereHas('pembelajaran.tahunAjaran', fn($q) => $q->where('id', session('tahun_ajaran_id')))
            ->orderBy('jam_mulai')
            ->orderBy('hari');

        if ($this->filter_user_id) {
            $query->where('user_id', $this->filter_user_id);
        }

        $jadwals = $query->get();
        // Get unique time slots
        $timeSlots = [];
        foreach ($jadwals as $j) {
            $key = substr($j->jam_mulai, 0, 5) . '-' . substr($j->jam_selesai, 0, 5);
            if (!isset($timeSlots[$key])) {
                $timeSlots[$key] = [
                    'mulai' => substr($j->jam_mulai, 0, 5),
                    'selesai' => substr($j->jam_selesai, 0, 5),
                ];
            }
        }

        // Sort time slots based on mulai time
        usort($timeSlots, function ($a, $b) {
            return strcmp($a['mulai'], $b['mulai']);
        });

        // Group by time slot and day
        $matrix = [];
        foreach ($jadwals as $j) {
            $timeKey = substr($j->jam_mulai, 0, 5) . '-' . substr($j->jam_selesai, 0, 5);
            $hari = $j->hari;
            $matrix[$timeKey][$hari][] = $j;
        }

        $users = User::orderBy('name')->get();

        return compact('timeSlots', 'matrix', 'users');
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Jadwal Pelajaran</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Jadwal pelajaran seluruh kelas dan pengampu {{ session('tahun_ajaran_nama') ? '• Tahun Ajaran ' . session('tahun_ajaran_nama') : '' }}</p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        {{-- TOOLBAR: FILTER GURU & CETAK --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/80 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-cyan-400 flex items-center justify-center text-sm border border-sky-500/20 shadow-2xs">
                    <i class="bi bi-calendar3"></i>
                </div>
                <div>
                    <h6 class="text-xs font-bold text-slate-800 dark:text-slate-100 font-display m-0">Matriks Jadwal Mingguan</h6>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Filter berdasarkan guru atau cetak format landscape</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <select wire:model.live="filter_user_id" class="form-select form-select-sm rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs font-medium" style="min-width: 200px;">
                    <option value="">-- Semua Guru --</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <a href="{{ route('jadwal.print', $filter_user_id ? ['user_id' => $filter_user_id] : []) }}" target="_blank" class="btn btn-sm btn-outline-success rounded-xl px-4 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                    <i class="bi bi-printer"></i>
                </a>
            </div>
        </div>

        {{-- Petunjuk Geser di Layar HP --}}
        <div class="md:hidden text-[11px] text-slate-500 dark:text-slate-400 mb-2 flex items-center justify-between px-1">
            <span class="flex items-center gap-1 font-medium">
                <i class="bi bi-arrows-expand"></i> Geser tabel ke kanan untuk melihat jadwal 7 hari
            </span>
            <span class="text-[10px] text-slate-400">Senin - Ahad</span>
        </div>

        <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="table-bordered mb-0 table text-center align-middle" style="font-size: 0.85rem; min-width: 800px;">
                <thead class="font-display">
                    <tr class="bg-slate-100/90 dark:bg-slate-800/90 text-xs text-slate-700 dark:text-slate-300">
                        <th rowspan="2" class="align-middle font-bold py-3" style="width: 14%;">WAKTU</th>
                        <th colspan="7" class="border-bottom-0 py-2.5 font-bold uppercase tracking-wider text-xs">HARI</th>
                    </tr>
                    <tr>
                        <th style="background-color: #2563eb !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">SENIN</th>
                        <th style="background-color: #334155 !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">SELASA</th>
                        <th style="background-color: #0284c7 !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">RABU</th>
                        <th style="background-color: #f59e0b !important; color: #0f172a !important;" class="text-dark text-uppercase py-2 text-xs font-bold">KAMIS</th>
                        <th style="background-color: #10b981 !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">JUMAT</th>
                        <th style="background-color: #64748b !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">SABTU</th>
                        <th style="background-color: #ef4444 !important; color: #ffffff !important;" class="text-white text-uppercase py-2 text-xs font-bold">AHAD</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timeSlots as $index => $slot)
                        @php
                            $timeKey = $slot['mulai'] . '-' . $slot['selesai'];
                        @endphp
                        <tr>
                            <td class="font-semibold text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30">
                                <i class="bi bi-clock me-1 text-sky-500"></i>
                                {{ substr($slot['mulai'], 0, 5) }} - {{ substr($slot['selesai'], 0, 5) }}
                            </td>
                            @for ($hari = 1; $hari <= 7; $hari++)
                                <td style="min-width: 120px; vertical-align: top;" class="p-2">
                                    @if (isset($matrix[$timeKey][$hari]))
                                        <div class="d-flex flex-column gap-2">
                                            @foreach ($matrix[$timeKey][$hari] as $item)
                                                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 p-2.5 text-start shadow-xs">
                                                    <div class="font-bold text-sky-600 dark:text-cyan-400 text-xs">
                                                        {{ $item->pembelajaran->keterangan }}
                                                    </div>
                                                    @if (!$filter_user_id)
                                                        <div class="small text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800 mt-1 pt-1 flex items-center gap-1 text-[11px]">
                                                            <i class="bi bi-person text-slate-400"></i>
                                                            <span>{{ $item->user->name ?? '-' }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    @if (empty($timeSlots))
                        <tr>
                            <td colspan="8" class="text-slate-500 dark:text-slate-400 py-10 text-center">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-slate-400"></i>
                                Belum ada jadwal pelajaran yang tersedia.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
