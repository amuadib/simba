@php
    $color = [7 => 'secondary', 8 => 'info', 9 => 'success', 10 => 'primary'];
@endphp
@extends('layouts.app')

@section('title', 'Input Nilai Jurnal ' . $jurnal->materi)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Nilai</h4>
            <small class="text-muted">Input Nilai Jurnal {{ $jurnal->materi }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('pembelajaran.jurnal.nilai.store', [$pembelajaran, $jurnal]) }}">
                @csrf
                <table class="table-bordered table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pembelajaran->anggota as $s)
                            <tr data-id="{{ $s->siswa->id }}">
                                <td>{{ $s->siswa->nama }}</td>
                                <td>
                                    <div class="input-group">
                                        @foreach ([7, 8, 9, 10] as $n)
                                            <button type="button"
                                                class="btn btn-sm btn-{{ $color[$n] }} btn-preset">{{ $n }}</button>
                                        @endforeach
                                        <input name="nilai[{{ $s->siswa->id }}]" type="number" min="0"
                                            max="100" class="form-control form-control-sm"
                                            value="{{ $nilai[$s->siswa->id] ?? '' }}">
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', (e) => {
            const cell = e.target.closest('.btn-preset');
            const id = cell.closest('tr').dataset.id
            document.querySelector('input[name="nilai[' + id + ']"]').value = cell.textContent + "0"
        })
    </script>
@endpush
