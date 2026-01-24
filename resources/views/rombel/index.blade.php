@extends('layouts.app')

@section('title', 'Data Rombel')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Rombel</h4>
            <small class="text-muted">Daftar seluruh Rombel</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
                <form method="post" action="{{ route('rombel.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required
                            value="{{ $data->nama }}"></div>
                    <div class="col">
                        <select name="tingkat" id="tingkat"class="form-control">
                            <option value="0">--Pilih--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}" {{ $data->tingkat == $t ? 'selected' : '' }}>
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button class="btn btn-warning"><i class="bi bi-pencil"></i> Update</button></div>
                </form>
            @else
                <form method="post" action="{{ route('rombel.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>
                    <div class="col">
                        <select name="tingkat" id="tingkat"class="form-control">
                            <option value="0">--Pilih--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif
            <table class="table-bordered table">
                <tr>
                    <th>Nama</th>
                    <th>Tingkat</th>
                    <th>Aksi</th>
                </tr>
                @if ($rombel->isEmpty())
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data</td>
                    </tr>
                @else
                    @foreach ($rombel as $s)
                        <tr>
                            <td>{{ $s->nama }}</td>
                            <td>{{ $s->tingkat }}</td>
                            <td width="200">
                                <a href="{{ route('rombel.edit', $s->id) }}" class="btn btn-sm btn-outline-warning"> <i
                                        class="bi bi-pencil"></i> </a>
                                <form action="{{ route('rombel.destroy', $s->id) }}" method="post" class="d-inline">
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
                {{ $rombel->links() }}
            </nav>
        </div>
    </div>
@endsection
