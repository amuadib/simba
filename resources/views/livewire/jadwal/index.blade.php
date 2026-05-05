<?php

use App\Models\Jadwal;
use App\Models\User;
use App\Models\TahunAjaran;
use Livewire\Volt\Component;

new class extends Component {
    public $filter_user_id = '';
    public $filter_tahun_ajaran_id = '';

    public function with()
    {
        $query = Jadwal::with(['pembelajaran.pelajaran', 'pembelajaran.tahunAjaran', 'user'])
            ->orderBy('jam_mulai')
            ->orderBy('hari');

        if ($this->filter_user_id) {
            $query->where('user_id', $this->filter_user_id);
        }

        if ($this->filter_tahun_ajaran_id) {
            $query->whereHas('pembelajaran', function ($q) {
                $q->where('tahun_ajaran_id', $this->filter_tahun_ajaran_id);
            });
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
        $tahunAjarans = TahunAjaran::orderBy('nama', 'desc')->get();

        return compact('timeSlots', 'matrix', 'users', 'tahunAjarans');
    }
};
?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Jadwal Pelajaran</h4>
            <small class="text-muted">Jadwal Pelajaran seluruh kelas dan guru</small>
        </div>

        <div class="d-flex gap-2">
            <select wire:model.live="filter_tahun_ajaran_id" class="form-select form-select-sm shadow-sm">
                <option value="">Semua Tahun Ajaran</option>
                @foreach ($tahunAjarans as $ta)
                    <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                @endforeach
            </select>
            <select wire:model.live="filter_user_id" class="form-select form-select-sm shadow-sm">
                <option value="">Semua Guru</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <a href="{{ route('jadwal.print') }}" target="_blank" class="btn btn-sm btn-success text-nowrap shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-bordered mb-0 table text-center align-middle">
                    <thead>
                        <tr>
                            <th rowspan="2" class="align-middle" style="width: 15%;">WAKTU</th>
                            <th colspan="6" class="border-bottom-0">HARI</th>
                        </tr>
                        <tr>
                            <th class="bg-primary text-white">SENIN</th>
                            <th class="bg-dark text-white">SELASA</th>
                            <th class="bg-info text-white">RABU</th>
                            <th class="bg-warning text-dark">KAMIS</th>
                            <th class="bg-success text-white">JUMAT</th>
                            <th class="bg-secondary text-white">SABTU</th>
                            <th class="bg-danger text-white">AHAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($timeSlots as $index => $slot)
                            @php
                                $timeKey = $slot['mulai'] . '-' . $slot['selesai'];
                            @endphp
                            <tr>
                                <td class="fw-bold">
                                    <i class="bi bi-clock me-1"></i>
                                    {{ substr($slot['mulai'], 0, 5) }} - {{ substr($slot['selesai'], 0, 5) }}
                                </td>
                                @for ($hari = 1; $hari <= 7; $hari++)
                                    <td style="min-width: 120px; vertical-align: top;" class="p-2">
                                        @if (isset($matrix[$timeKey][$hari]))
                                            <div class="d-flex flex-column gap-2">
                                                @foreach ($matrix[$timeKey][$hari] as $item)
                                                    <div class="rounded border bg-white p-2 text-start shadow-sm">
                                                        <div class="fw-bold text-primary">
                                                            {{ $item->pembelajaran->keterangan }}</div>
                                                        @if (!$filter_user_id)
                                                            <div class="small text-muted border-top mt-1 pt-1">
                                                                <i
                                                                    class="bi bi-person me-1"></i>{{ $item->user->name ?? '-' }}
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
                                <td colspan="7" class="text-muted fw-normal p-5 text-center">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
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
