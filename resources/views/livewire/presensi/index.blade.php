<?php

use App\Models\Pembelajaran;
use App\Models\Presensi;
use App\Models\Siswa;
use Illuminate\Support\Str;
use App\Models\TahunAjaran;
use Livewire\Volt\Component;

new class extends Component {
    public $bulan = '';
    public $pembelajaran_id = '';
    public $tglList = [];
    public $rekap = [];

    public function mount()
    {
        $this->pembelajaran_id = request()->pembelajaran_id ?? '';
        $this->bulan = request()->bulan ?? date('Y-m');
        $this->loadRekap();
    }

    public function updatedPembelajaranId()
    {
        $this->loadRekap();
    }

    public function updatedBulan()
    {
        $this->loadRekap();
    }

    public function loadRekap()
    {
        $this->rekap = [];
        $this->tglList = [];

        if ($this->pembelajaran_id) {
            $start = "$this->bulan-01";
            $end = date('Y-m-t', strtotime($start));

            $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), new \DateTime($end)->modify('+1 day'));

            foreach ($period as $d) {
                $this->tglList[] = $d->format('Y-m-d');
            }

            $presensiData = Presensi::join('siswa', 'presensi.siswa_id', '=', 'siswa.id')
                ->where('presensi.pembelajaran_id', $this->pembelajaran_id)
                ->where('presensi.tanggal', 'like', $this->bulan . '-%')
                ->select('presensi.*', 'siswa.nama', 'siswa.panggilan', 'siswa.jenis_kelamin')
                ->get();

            // Get all members even if they don't have presensi in this month yet
            $members = App\Models\AnggotaPembelajaran::where('pembelajaran_id', $this->pembelajaran_id)->with('siswa')->get();

            foreach ($members as $m) {
                $s = $m->siswa;
                $id = $s->id;
                $this->rekap[$id] = [
                    'id' => $id,
                    'nama_processed' => setNama($s->nama, $s->panggilan, $s->jenis_kelamin),
                    'nama' => $s->nama,
                    'H' => 0,
                    'I' => 0,
                    'S' => 0,
                    'A' => 0,
                    'tgl' => [],
                ];
            }

            foreach ($presensiData as $p) {
                $id = $p->siswa_id;
                if (!isset($this->rekap[$id])) {
                    continue;
                }

                $this->rekap[$id]['tgl'][$p->tanggal] = $p->status;
                $this->rekap[$id][$p->status]++;
            }

            // Sort by name
            uasort($this->rekap, fn($a, $b) => strnatcasecmp($a['nama'], $b['nama']));
        }
    }

    public function updateCell($siswaId, $tanggal, $status)
    {
        if ($status == '-') {
            Presensi::where('siswa_id', $siswaId)->where('pembelajaran_id', $this->pembelajaran_id)->where('tanggal', $tanggal)->delete();
        } else {
            Presensi::upsert(
                [
                    [
                        'id' => (string) Str::uuid(),
                        'siswa_id' => $siswaId,
                        'pembelajaran_id' => $this->pembelajaran_id,
                        'tanggal' => $tanggal,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                ['siswa_id', 'pembelajaran_id', 'tanggal'],
                ['status', 'updated_at'],
            );
        }

        $this->loadRekap();
    }

    public function getPembelajaranListProperty()
    {
        return Pembelajaran::join('tahun_ajaran', 'tahun_ajaran.id', '=', 'pembelajaran.tahun_ajaran_id')->where('tahun_ajaran.aktif', 'y')->select('pembelajaran.*')->orderBy('pembelajaran.keterangan')->get();
    }
};

?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi</h4>
            <small class="text-muted">Rekap Presensi Bulanan</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="d-print-block d-none mb-4">
                Rekap Presensi Bulan {{ \Carbon\Carbon::parse($bulan)->locale('id_ID')->isoFormat('MMMM YYYY') }}
            </h5>

            <div class="row g-2 d-print-none mb-3">
                <div class="col-auto">
                    <input type="month" wire:model.live="bulan" class="form-control">
                </div>
                <div class="col-auto">
                    <select wire:model.live="pembelajaran_id" class="form-select" id="pembelajaran_id">
                        <option value="">--Pilih Pembelajaran--</option>
                        @foreach ($this->pembelajaranList as $p)
                            <option value="{{ $p->id }}">{{ $p->keterangan }}</option>
                        @endforeach
                    </select>
                </div>
                @if (count($rekap) > 0)
                    <div class="col-auto">
                        <a href="{{ route('pembelajaran.presensi.export', ['pembelajaran_id' => $pembelajaran_id, 'bulan' => $bulan]) }}"
                            class="btn btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i> EXCEL
                        </a>
                    </div>
                    <div class="col-auto">
                        <button type="button" onclick="window.print()" class="btn btn-secondary"><i
                                class="bi bi-printer me-1"></i> PRINT</button>
                    </div>
                @endif
            </div>

            @if (count($rekap) > 0)
                <div class="table-responsive">
                    <table class="table-bordered table-sm table align-middle" x-data="{
                        statusCycle: ['-', 'H', 'A', 'S', 'I'],
                        updateCell(siswaId, tanggal, current) {
                            const next = this.statusCycle[(this.statusCycle.indexOf(current) + 1) % this.statusCycle.length];
                            $wire.updateCell(siswaId, tanggal, next);
                        }
                    }">
                        <thead class="table-light">
                            <tr>
                                <th class="px-2 py-2">Nama</th>
                                @foreach ($tglList as $t)
                                    <th class="small text-center">{{ date('d', strtotime($t)) }}</th>
                                @endforeach
                                <th class="text-success text-center">H</th>
                                <th class="text-warning text-center">I</th>
                                <th class="text-info text-center">S</th>
                                <th class="text-danger text-center">A</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rekap as $r)
                                <tr wire:key="rekap-{{ $r['id'] }}">
                                    <td class="fw-medium small text-nowrap px-2">{!! $r['nama_processed'] !!}</td>
                                    @foreach ($tglList as $t)
                                        @php $status = $r['tgl'][$t] ?? '-'; @endphp
                                        <td class="status-{{ $status }} sel presensi"
                                            @click="updateCell('{{ $r['id'] }}', '{{ $t }}', '{{ $status }}')"
                                            wire:loading.class="opacity-50 pointer-events-none">
                                            {{ $status }}
                                        </td>
                                    @endforeach
                                    <td class="fw-bold text-success bg-light text-center">{{ $r['H'] }}</td>
                                    <td class="fw-bold text-warning bg-light text-center">{{ $r['I'] }}</td>
                                    <td class="fw-bold text-info bg-light text-center">{{ $r['S'] }}</td>
                                    <td class="fw-bold text-danger bg-light text-center">{{ $r['A'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info py-4 text-center">
                    <i class="bi bi-info-circle fs-2 d-block mb-2"></i>
                    Pilih Pembelajaran untuk menampilkan rekap presensi.
                </div>
            @endif
        </div>
    </div>
    <style>
        .sel {
            cursor: pointer;
            text-align: center;
            touch-action: manipulation;
            width: 25px;
            height: 30px;
            font-size: 0.75rem;
        }

        .presensi {
            text-align: center;
            font-weight: 600;
            transition: all .15s ease;
            border: 1px solid #dee2e6;
        }

        .status-H {
            color: #22c55e !important;
            background: rgba(34, 197, 94, .1);
        }

        .status-I {
            color: #38bdf8 !important;
            background: rgba(56, 189, 248, .1);
        }

        .status-S {
            color: #facc15 !important;
            background: rgba(250, 204, 21, .1);
        }

        .status-A {
            color: #ef4444 !important;
            background: rgba(239, 68, 68, .1);
        }

        .status-- {
            color: #dee2e6;
        }

        .presensi:hover {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: 10;
            position: relative;
        }

        @media print {

            header,
            .sidebar,
            .d-print-none,
            .btn {
                display: none !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            table {
                font-size: 8pt !important;
                border-collapse: collapse !important;
                width: 100% !important;
            }

            .status-H,
            .status-I,
            .status-S,
            .status-A {
                background: transparent !important;
                color: #000 !important;
                font-weight: bold;
            }
        }
    </style>
</div>
