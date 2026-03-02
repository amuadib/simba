@extends('layouts.app')

@section('title', 'Data Pembelajaran')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Pembelajaran</h4>
            <small class="text-muted">Daftar seluruh Pembelajaran</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
                <form method="post" action="{{ route('pembelajaran.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col-md-3">
                        <select name="tahun_ajaran_id" class="form-select" required>
                            <option value="">--Pilih Tahun Ajaran--</option>
                            @foreach ($tahunajaran as $ta)
                                <option value="{{ $ta->id }}"
                                    {{ $data->tahun_ajaran_id == $ta->id ? 'selected' : '' }}>
                                    {{ $ta->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="pelajaran_id" class="form-select" required>
                            <option value="">--Pilih Pelajaran--</option>
                            @foreach ($pelajaran as $p)
                                <option value="{{ $p->id }}" {{ $data->pelajaran_id == $p->id ? 'selected' : '' }}>
                                    {{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input name="keterangan" class="form-control" placeholder="Nama" value="{{ $data->keterangan }}">
                    </div>
                    <div class="col-md-2"><button class="btn btn-warning"><i class="bi bi-pencil"></i> Update</button></div>
                </form>
            @else
                <form method="post" action="{{ route('pembelajaran.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col-md-2">
                        <select name="tahun_ajaran_id" class="form-select" required>
                            <option value="">--Pilih Tahun Ajaran--</option>
                            @foreach ($tahunajaran as $ta)
                                <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="pelajaran_id" class="form-select" required onchange="addToKeterangan()">
                            <option value="">--Pilih Pelajaran--</option>
                            @foreach ($pelajaran as $p)
                                <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="kelas_id" class="form-select" onchange="addToKeterangan()">
                            <option value="">--Pilih Kelas--</option>
                            @foreach ($kelas as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input name="keterangan" class="form-control" placeholder="Keterangan">
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                    <small class="text-muted">Pilih Kelas untuk menambahkan Anggota Otomatis</small>
                </form>
            @endif
            <table class="table-bordered table">
                <tr>
                    <th>Nama</th>
                    <th>Tahun Ajaran</th>
                    <th>Pelajaran</th>
                    <th>Anggota</th>
                    <th>Aksi</th>
                </tr>
                @if ($pembelajaran->isEmpty())
                    <tr>
                        <td colspan="5" class="text-center">Tidak ada data</td>
                    </tr>
                @else
                    @foreach ($pembelajaran as $s)
                        <tr>
                            <td>{{ $s->keterangan }}</td>
                            <td>{{ $s->tahunAjaran->nama }}</td>
                            <td>{{ $s->pelajaran->nama }}</td>
                            <td>{{ $s->anggota->count() }}</td>
                            <td>
                                <a href="{{ route('pembelajaran.jurnal.nilai.index', $s->id) }}"
                                    class="btn btn-sm btn-outline-danger"><i class="bi bi-star"></i> Nilai</a>
                                <a href="{{ route('pembelajaran.jurnal.index', $s->id) }}?tanggal={{ date('Y-m-d') }}"
                                    class="btn btn-sm btn-outline-warning"><i class="bi bi-book"></i> Jurnal</a>
                                <a href="{{ route('pembelajaran.presensi.create', $s->id) }}?tanggal={{ date('Y-m-d') }}"
                                    class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar"></i> Presensi</a>
                                <a href="{{ route('pembelajaran.anggota.index', $s->id) }}"
                                    class="btn btn-sm btn-outline-success"><i class="bi bi-people"></i> Anggota</a>
                                <a href="{{ route('pembelajaran.edit', $s->id) }}"
                                    class="btn btn-sm btn-outline-warning">&nbsp;<i class="bi bi-pencil"></i>&nbsp;</a>
                                <form action="{{ route('pembelajaran.destroy', $s->id) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Hapus data ini?')">&nbsp;<i
                                            class="bi bi-trash"></i>&nbsp;</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </table>

            <nav>
                {{ $pembelajaran->links() }}
            </nav>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function addToKeterangan() {
            const pelajaranSelect = document.querySelector('select[name="pelajaran_id"]');
            const kelasSelect = document.querySelector('select[name="kelas_id"]');
            const keteranganInput = document.querySelector('input[name="keterangan"]');

            const pelajaranText = pelajaranSelect.options[pelajaranSelect.selectedIndex].text;
            const kelasText = kelasSelect.options[kelasSelect.selectedIndex].text;

            let keterangan = '';
            if (pelajaranSelect.value) {
                keterangan += pelajaranText;
            }
            if (kelasSelect.value) {
                if (keterangan) {
                    keterangan += ' ';
                }
                keterangan += kelasText;
            }

            keteranganInput.value = keterangan;
        }
    </script>
@endpush
