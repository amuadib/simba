@extends('layouts.app')

@section('title', 'Rekap Presensi ')

@section('content')
    <h5>Rekap Presensi Bulanan</h5>

    <form method="GET" class="row g-2 no-print mb-3">
        @csrf
        <div class="col-auto">
            <input type="month" name="bulan" value="{{ $bulan }}" class="form-control" onchange="this.form.submit()">
        </div>
        <div class="col-auto">
            <select name="pembelajaran_id" class="form-select" onchange="this.form.submit()">
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
        <div class="col-auto"><a class="btn btn-success" href="?<?= http_build_query($_GET + ['export' => 1]) ?>">Export
                Excel</a></div>
        <div class="col-auto"><button type="button" onclick="print()" class="btn btn-secondary">Print</button></div>
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
                    <td>{{ $r['nama'] }}</td>
                    @foreach ($tglList as $t)
                        <td class="{{ $statusColor[$r['tgl'][$t] ?? '-'] }} text-center">{{ $r['tgl'][$t] ?? '-' }}</td>
                    @endforeach
                    <td class="text-success">{{ $r['H'] }}</td>
                    <td class="text-warning">{{ $r['I'] }}</td>
                    <td class="text-info">{{ $r['S'] }}</td>
                    <td class="text-danger">{{ $r['A'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="alert alert-info">Tidak ada data presensi.</div>
    @endif
    <style>
        @media print {
            @page {
                size: A4 landscape;
            }

            form {
                display: none
            }
        }
    </style>
@endsection
