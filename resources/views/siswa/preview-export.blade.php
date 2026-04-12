<div class="table-responsive">
    <table class="table table-hover align-middle border">
        <thead class="table-light">
            <tr>
                <th>Nama</th>
                <th>Rombel</th>
                <th width="80" class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($siswa as $s)
                <tr>
                    <td>
                        <div class="fw-bold">{!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}</div>
                        <small class="text-muted">{{ $s->nisn ?: '-' }}</small>
                    </td>
                    <td><span class="badge bg-secondary opacity-75">{{ $s->rombel->nama }}</span></td>
                    <td class="text-center">
                        <button type="button" 
                                class="btn btn-sm btn-outline-danger btn-hapus-preview" 
                                data-id="{{ $s->id }}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center py-4 text-muted">Belum ada siswa yang dipilih</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($siswa->count() > 0)
    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
        <button type="button" class="btn btn-danger btn-sm" id="btn-kosongkan-preview">
            <i class="bi bi-trash me-1"></i> Kosongkan
        </button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <a href="{{ route('siswa.export') }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Download Excel ({{ $siswa->count() }})
            </a>
        </div>
    </div>
@else
    <div class="text-end mt-3">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
    </div>
@endif
