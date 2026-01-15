@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <h5>Data Siswa</h5>
    @if ($action == 'edit')
        <form method="post" action="{{ route('siswa.update', $data) }}" class="row g-2 mb-3">
            @csrf @method('PUT')
            <div class="col"><input name="nama" class="form-control" placeholder="Nama" required
                    value="{{ $data->nama }}"></div>
            <div class="col"><input name="nisn" class="form-control" placeholder="NISN" value="{{ $data->nisn }}">
            </div>
            <div class="col">
                <select name="rombel_id" class="form-select">
                    <option value="">--Pilih--</option>
                    @foreach ($rombel as $k)
                        <option value="{{ $k->id }}" {{ $data->rombel_id == $k->id ? 'selected' : '' }}>
                            {{ $k->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-warning">Edit</button></div>
        </form>
    @else
        <form method="post" action="{{ route('siswa.store') }}" class="row g-2 mb-3">
            @csrf
            <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>
            <div class="col"><input name="nisn" class="form-control" placeholder="NISN"></div>
            <div class="col">
                <select name="rombel_id" class="form-select">
                    <option value="">--Pilih--</option>
                    @foreach ($rombel as $k)
                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary">Tambah</button></div>
        </form>
    @endif
    <form method="post" action="{{ route('siswa.import') }}" class="row g-2 mb-3" enctype="multipart/form-data">
        @csrf
        <div class="col">
            <input type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
        </div>
        <div class="col">
            <select name="rombel_id" class="form-select">
                <option value="">--Pilih--</option>
                @foreach ($rombel as $k)
                    <option value="{{ $k->id }}">{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-warning">Impor</button></div>
    </form>

    <form method="get" action="{{ route('siswa.index') }}" class="row g-2 mb-3" id='filterForm'>
        @csrf
        <div class="col">
            <select name="rombel_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                <option value="">--Pilih--</option>
                @foreach ($rombel as $k)
                    <option value="{{ $k->id }}" {{ request('rombel_id') == $k->id ? 'selected' : '' }}>
                        {{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-success">Filter</button></div>
    </form>
    <table class="table-bordered table">
        <tr>
            <th>Nama</th>
            <th>NISN</th>
            <th>Rombel</th>
            <th>Aksi</th>
        </tr>
        @foreach ($siswa as $s)
            <tr>
                <td>{{ $s->nama }}</td>
                <td>{{ $s->nisn }}</td>
                <td>{{ $s->rombel->nama }}</td>
                <td width="200">
                    <a href="{{ route('siswa.edit', $s->id) }}" class="btn btn-sm btn-default">📝</a>
                    <form action="{{ route('siswa.destroy', $s->id) }}" method="post" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-default"
                            onclick="return confirm('Hapus data ini?')">❌</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>

    <nav>
        {{ $siswa->links() }}
    </nav>

@endsection
