@extends('layouts.app')

@section('title', 'Rekap Presensi ')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi</h4>
            <small class="text-muted">Rekap Presensi Bulanan</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="d-print-block d-none">
                Rekap Presensi Bulan {{ \Carbon\Carbon::parse($bulan)->locale('id_ID')->isoFormat('MMMM YYYY') }}
            </h5>

            <form method="GET" class="row g-2 d-print-none mb-3">
                @csrf
                <div class="col-auto">
                    <input type="month" name="bulan" value="{{ $bulan }}" class="form-control"
                        onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <select name="pembelajaran_id" id="pembelajaran_id" class="form-select" onchange="this.form.submit()">
                        <option value="">--Pilih Pembelajaran--</option>
                        @foreach ($pembelajaran_list as $p)
                            <option value="{{ $p->id }}"
                                {{ request()->pembelajaran_id == $p->id ? 'selected' : '' }}>
                                {{ $p->keterangan }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Tampilkan</button>
                </div>
                @if (count($rekap) > 0)
                    <div class="col-auto">
                        <button class="btn btn-success" type="button"
                            onclick="document.getElementById('export-form').submit()">
                            Export Excel
                        </button>
                    </div>
                    <div class="col-auto"><button type="button" onclick="print()" class="btn btn-secondary">Print</button>
                    </div>
                @endif
            </form>

            @if (count($rekap) > 0)
                <form method="get" action="{{ route('pembelajaran.presensi.export') }}" target="_blank" id="export-form">
                    <input type="hidden" name="pembelajaran_id" value="{{ request()->pembelajaran_id }}">
                    <input type="hidden" name="bulan" value="{{ request()->bulan }}">
                </form>
                <table class="table-bordered table-sm table">
                    <tr>
                        <th>Nama</th>
                        @foreach ($tglList as $t)
                            <th>{{ date('d', strtotime($t)) }}</th>
                        @endforeach
                        <th>H</th>
                        <th>I</th>
                        <th>S</th>
                        <th>A</th>
                    </tr>
                    @foreach ($rekap as $r)
                        <tr>
                            <td data-siswa="{{ $r['id'] }}">{{ $r['nama'] }}</td>
                            @foreach ($tglList as $t)
                                <td class="status-{{ $r['tgl'][$t] ?? '-' }} sel presensi"
                                    data-tanggal="{{ $t }}">
                                    {{ $r['tgl'][$t] ?? '-' }}</td>
                            @endforeach
                            <td class="text-success total-h" data-row="{{ $r['id'] }}">{{ $r['H'] }}</td>
                            <td class="text-warning total-i" data-row="{{ $r['id'] }}">{{ $r['I'] }}</td>
                            <td class="text-info total-s" data-row="{{ $r['id'] }}">{{ $r['S'] }}</td>
                            <td class="text-danger total-a" data-row="{{ $r['id'] }}">{{ $r['A'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="alert alert-info">Tidak ada data presensi.</div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .sel {
            cursor: pointer;
            text-align: center;
            touch-action: manipulation;
        }

        /* BASE */
        .presensi {
            text-align: center;
            font-weight: 600;
            border-radius: 6px;
            transition: all .15s ease;
        }

        /* HADIR */
        .status-H {
            color: #22c55e !important;
            background: rgba(34, 197, 94, .12);
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .35);
        }

        /* IZIN */
        .status-I {
            color: #38bdf8 !important;
            background: rgba(56, 189, 248, .12);
            box-shadow: inset 0 0 0 1px rgba(56, 189, 248, .35);
        }

        /* SAKIT */
        .status-S {
            color: #facc15 !important;
            background: rgba(250, 204, 21, .12);
            box-shadow: inset 0 0 0 1px rgba(250, 204, 21, .35);
        }

        /* ALPA */
        .status-A {
            color: #ef4444 !important;
            background: rgba(239, 68, 68, .14);
            box-shadow: inset 0 0 0 1px rgba(239, 68, 68, .4);
        }

        .presensi:hover,
        .presensi:focus-visible {
            transform: scale(1.03);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, .15),
                0 0 12px currentColor;
        }

        /* Mobile tap feedback */
        .presensi:active {
            transform: scale(.97);
            filter: brightness(1.1);
        }

        .presensi.saved {
            animation: pulse 0.6s ease;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 currentColor;
            }

            100% {
                box-shadow: 0 0 0 12px transparent;
            }
        }

        @media print {

            /* Paksa light mode */
            :root,
            [data-theme="dark"] {
                --bg-main: #ffffff !important;
                --bg-card: #ffffff !important;
                --text-main: #000000 !important;
            }

            body {
                background: #fff !important;
                color: #000 !important;
            }

            /* Table */
            table {
                background: #fff !important;
                color: #000 !important;
            }

            th,
            td {
                color: #000 !important;
                box-shadow: none !important;
            }

            /* Matikan glow */
            .presensi {
                background: transparent !important;
                box-shadow: none !important;
                color: #000 !important;
                transform: none !important;
            }

            /* Hilangkan hover/klik UI */
            button,
            .btn,
            .presensi-controls {
                display: none !important;
            }
        }
    </style>
@endpush
@push('scripts')
    <script>
        const statusCycle = ['-', 'H', 'A', 'S', 'I'];
        document.addEventListener('pointerup', function(e) {
            const cell = e.target.closest('.sel');
            if (!cell) return;

            e.preventDefault();
        });

        document.addEventListener('click', function(e) {
            const cell = e.target.closest('.sel');
            if (!cell) return;

            const current = cell.innerText;
            const siswaId = e.target.closest('tr').querySelector('td:first-child').dataset.siswa;
            const next = statusCycle[(statusCycle.indexOf(current) + 1) % statusCycle.length];
            const url = "{{ route('pembelajaran.presensi.update') }}";

            // optimistic UI (langsung berubah)
            updateCellUI(cell, next);
            updateTotals(siswaId, current, next);

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        siswa_id: siswaId,
                        pembelajaran_id: document.getElementById('pembelajaran_id').value,
                        tanggal: cell.dataset.tanggal,
                        status: next
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) throw 'failed';
                })
                .catch(() => {
                    // rollback jika gagal
                    updateCellUI(cell, current);
                    alert('Gagal update presensi');
                });
        });

        function updateCellUI(cell, status) {
            cell.textContent = status;
            cell.classList.remove('status-H', 'status-S', 'status-I', 'status-A', 'status--');
            cell.classList.add('status-' + status || '');
            cell.classList.add('saved');
            setTimeout(() => cell.classList.remove('saved'), 600);
        }

        function updateTotals(siswaId, oldStatus, newStatus) {
            if (oldStatus === newStatus) return;

            const dec = document.querySelector(`.total-${oldStatus.toLowerCase()}[data-row="${siswaId}"]`);
            const inc = document.querySelector(`.total-${newStatus.toLowerCase()}[data-row="${siswaId}"]`);

            if (dec) dec.textContent = Math.max(0, Number(dec.textContent) - 1);
            if (inc) inc.textContent = Number(inc.textContent) + 1;
        }
    </script>
@endpush
