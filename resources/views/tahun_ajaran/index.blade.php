@extends('layouts.app')

@section('title', 'Data Tahun Ajaran')

@section('content')
    <h5>Data Tahun Ajaran</h5>
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
                <form method="post" action="{{ route('tahun_ajaran.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col">
                        <input name="nama" class="form-control" placeholder="Nama" required value="{{ $data->nama }}">
                    </div>
                    <div class="col">
                        <div class="form-check-inline">
                            <input class="form-check-input" type="radio" name="aktif" id="aktif_y" value="y"
                                {{ $data->aktif == 'y' ? 'checked' : '' }}>
                            <label class="form-check-label" for="aktif_y">
                                Aktif
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <input class="form-check-input" type="radio" name="aktif" id="aktif_n" value="n"
                                {{ $data->aktif == 'n' ? 'checked' : '' }}>
                            <label class="form-check-label" for="aktif_n">
                                Tidak Aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-auto"><button class="btn btn-warning">Update</button></div>
                </form>
            @else
                <form method="post" action="{{ route('tahun_ajaran.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>

                    <div class="col">
                        <div class="form-check-inline">
                            <input class="form-check-input" type="radio" name="aktif" id="aktif_y" value="y">
                            <label class="form-check-label" for="aktif_y">
                                Aktif
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <input class="form-check-input" type="radio" name="aktif" id="aktif_n" value="n"
                                checked>
                            <label class="form-check-label" for="aktif_n">
                                Tidak Aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-auto"><button class="btn btn-primary">Tambah</button></div>
                </form>
            @endif
            <table class="table-bordered table">
                <tr>
                    <th>Nama</th>
                    <th>Aktif</th>
                    <th>Aksi</th>
                </tr>
                @if ($tahunajaran->isEmpty())
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data</td>
                    </tr>
                @else
                    @foreach ($tahunajaran as $s)
                        <tr>
                            <td>{{ $s->nama }}</td>
                            <td>{{ $s->aktif == 'y' ? '✅' : '🚫' }}</td>
                            <td width="200">
                                <a href="{{ route('tahun_ajaran.edit', $s->id) }}" class="btn btn-sm btn-default">📝</a>
                                <form action="{{ route('tahun_ajaran.destroy', $s->id) }}" method="post" class="d-inline">
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
                {{ $tahunajaran->links() }}
            </nav>
        </div>
    </div>
@endsection
