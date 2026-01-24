@extends('layouts.app')

@section('title', 'Data Pelajaran')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Pelajaran</h4>
            <small class="text-muted">Daftar seluruh Pelajaran</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
                <form method="post" action="{{ route('pelajaran.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required
                            value="{{ $data->nama }}"></div>
                    <div class="col-auto"><button class="btn btn-warning"><i class="bi bi-pencil"></i> Update</button></div>
                </form>
            @else
                <form method="post" action="{{ route('pelajaran.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>
                    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
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
                                <a href="{{ route('pelajaran.edit', $s->id) }}" class="btn btn-sm btn-outline-warning"> <i
                                        class="bi bi-pencil"></i> </a>
                                <form action="{{ route('pelajaran.destroy', $s->id) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Hapus data ini?')"> <i class="bi bi-trash"></i> </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </table>

            <nav>
                {{ $pelajaran->links() }}
            </nav>
        </div>
    </div>
@endsection
