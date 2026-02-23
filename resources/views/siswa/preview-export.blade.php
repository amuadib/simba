<div class="container">

    @if (count($siswa) > 0)
        <div class="mb-3">
            <a href="{{ route('siswa.export') }}" class="btn btn-success btn-sm">
                <i class="bi bi-download"></i>
                Ekspor</a>
            <button type="button" class="btn btn-danger btn-sm" onclick="hapusSiswaPreviewExport('all')">
                <i class="bi bi-trash"></i>
                Hapus Semua</button>
            <a href="{{ route('siswa.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
                Batal</a>
        </div>
    @endif
    <div class="table-responsive">
        <table class="table-bordered table-striped table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NISN</th>
                    <th>Nama Lengkap</th>
                    <th>Rombel</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($siswa as $index => $data)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $data->nisn }}</td>
                        <td>{{ $data->nama }}</td>
                        <td>{{ $data->rombel->nama }}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm"
                                onclick="hapusSiswaPreviewExport('{{ $data->id }}', this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada data yang tersedia untuk diexport.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
