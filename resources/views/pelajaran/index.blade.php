@extends('layouts.app')

@section('title', 'Data Pelajaran')

@section('content')
    <h5>Data Pelajaran</h5>
    @if ($action == 'edit')
        <form method="post" action="{{ route('pelajaran.update', $data) }}" class="row g-2 mb-3">
            @csrf @method('PUT')
            <div class="col"><input name="nama" class="form-control" placeholder="Nama" required
                    value="{{ $data->nama }}"></div>
            <div class="col-auto"><button class="btn btn-warning">Update</button></div>
        </form>
    @else
        <form method="post" action="{{ route('pelajaran.store') }}" class="row g-2 mb-3">
            @csrf
            <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>
            <div class="col-auto"><button class="btn btn-primary">Tambah</button></div>
        </form>
    @endif
    <table class="table-bordered table">
        <tr>
            <th>Nama</th>
            <th>Aksi</th>
        </tr>
        @if ($pelajaran->isEmpty())
            <tr>
                <td colspan="2" class="text-center">Tidak ada data</td>
            </tr>
        @else
            @foreach ($pelajaran as $s)
                <tr>
                    <td>{{ $s->nama }}</td>
                    <td width="200">
                        <a href="{{ route('pelajaran.edit', $s->id) }}" class="btn btn-sm btn-default">📝</a>
                        <form action="{{ route('pelajaran.destroy', $s->id) }}" method="post" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-default"
                                onclick="return confirm('Hapus data ini?')">❌</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        @endif
    </table>

    <nav>
        {{ $pelajaran->links() }}
    </nav>

@endsection
