@extends('layouts.app')

@section('title', 'Daftar Nilai Pembelajaran ' . $pembelajaran->keterangan)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Nilai</h4>
            <small class="text-muted">Daftar Nilai Pembelajaran {{ $pembelajaran->keterangan }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table-bordered table">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-center align-middle">No</th>
                        <th rowspan="2" class="text-center align-middle">Siswa</th>
                        <th colspan="{{ count($jurnals) }}" class="text-center align-middle">Materi</th>
                    </tr>
                    <tr>
                        @foreach ($jurnals as $j)
                            <th>{{ $j['materi'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($anggota as $a)
                        <tr>
                            <td>{{ $loop->index + 1 }}</td>
                            <td>{{ $a->siswa->nama }} ({{ $a->siswa->rombel->nama }})</td>
                            @foreach ($jurnals as $key => $j)
                                <td>{{ $nilai[$a->siswa->id][$key] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>
@endsection
