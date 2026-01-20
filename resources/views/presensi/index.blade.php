@extends('layouts.app')

@section('title', 'Rekap Presensi ')

@section('content')
    <h5 class="d-print-none">Rekap Presensi Bulanan</h5>

    <h5 class="d-print-block d-none">
        Rekap Presensi Bulan {{ \Carbon\Carbon::parse($bulan)->locale('id_ID')->isoFormat('MMMM YYYY') }}
    </h5>

    <form method="GET" class="row g-2 d-print-none mb-3">
        @csrf
        <div class="col-auto">
            <input type="month" name="bulan" value="{{ $bulan }}" class="form-control" onchange="this.form.submit()">
        </div>
        <div class="col-auto">
            <select name="pembelajaran_id" id="pembelajaran_id" class="form-select" onchange="this.form.submit()">
                <option value="">--Pilih Pembelajaran--</option>
                @foreach ($pembelajaran_list as $p)
                    <option value="{{ $p->id }}" {{ request()->pembelajaran_id == $p->id ? 'selected' : '' }}>
                        {{ $p->keterangan }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Tampilkan</button>
        </div>
        @if (count($rekap) > 0)
            <div class="col-auto"><a class="btn btn-success" href="?<?= http_build_query($_GET + ['export' => 1]) ?>">Export
                    Excel</a></div>
            <div class="col-auto"><button type="button" onclick="print()" class="btn btn-secondary">Print</button></div>
        @endif
    </form>

    @if (count($rekap) > 0)
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
                        <td class="{{ $statusColor[$r['tgl'][$t] ?? '-'] }} sel" data-tanggal="{{ $t }}">
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
@endsection

@push('styles')
    <style>
        .sel {
            cursor: pointer;
            text-align: center;
            touch-action: manipulation;
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
            const styleMap = {!! json_encode($statusColor) !!};
            cell.textContent = status;
            cell.classList.remove(styleMap['H'], styleMap['I'], styleMap['S'], styleMap['A']);
            cell.classList.add(styleMap[status] || '');
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
