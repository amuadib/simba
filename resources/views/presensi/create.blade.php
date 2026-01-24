@extends('layouts.app')

@section('title', 'Presensi ' . $pembelajaran->keterangan)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi</h4>
            <small class="text-muted">Input Presensi {{ $pembelajaran->keterangan }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-2 mb-3" action="{{ route('pembelajaran.presensi.store', $pembelajaran->id) }}">
                @csrf
                <div class="col-md-3">
                    <input type="date" name="tanggal" id="tanggal" class="form-control" value="{{ request()->tanggal }}"
                        required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary" type="button" onclick="loadPresensi()">Tampilkan</button>
                </div>
                <table class="table-bordered visually-hidden table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="tabel-presensi">
                    </tbody>
                </table>

                <button class="btn btn-success visually-hidden">Simpan Presensi</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        loadPresensi();

        function loadPresensi() {
            const tanggal = document.getElementById('tanggal').value;
            const pembelajaran = "{{ $pembelajaran->id }}";
            const url = "{{ route('pembelajaran.presensi.load') }}";

            if (!tanggal) {
                alert('Tanggal wajib diisi');
                return;
            }

            const params = new URLSearchParams({
                tanggal: tanggal,
                pembelajaran_id: pembelajaran
            });

            fetch(url + '?' + params.toString())
                .then(response => response.json())
                .then(res => {
                    let html = '';
                    res.forEach((siswa, i) => {
                        html += `
                <tr>
                    <td>${siswa.nama}</td>
                    <td>
                        <div class="form-check-inline">
                                <input type="radio" name="data[${siswa.id}][status]" class="form-check-input"
                                    value="H"
                                    ${siswa.status === 'H' ? 'checked' : ''}>
                                <label class="form-check-label">H</label>
                            </div>
                        <div class="form-check-inline">
                                <input type="radio" name="data[${siswa.id}][status]" class="form-check-input"
                                    value="I"
                                    ${siswa.status === 'I' ? 'checked' : ''}>
                                <label class="form-check-label">I</label>
                            </div>
                        <div class="form-check-inline">
                                <input type="radio" name="data[${siswa.id}][status]" class="form-check-input"
                                    value="S"
                                    ${siswa.status === 'S' ? 'checked' : ''}>
                                <label class="form-check-label">S</label>
                            </div>
                        <div class="form-check-inline">
                                <input type="radio" name="data[${siswa.id}][status]" class="form-check-input"
                                    value="A"
                                    ${siswa.status === 'A' ? 'checked' : ''}>
                                <label class="form-check-label">A</label>
                            </div>
                    </td>
                    <td>
                        <textarea name="data[${siswa.id}][keterangan]" class="form-control" rows="1">${siswa.keterangan || ''}</textarea>
                    </td>
                </tr>`;
                    });

                    document.getElementById('tabel-presensi').innerHTML = html;
                    document.querySelector('table').classList.remove('visually-hidden');
                    document.querySelector('button.btn-success').classList.remove('visually-hidden');
                })
                .catch(err => {
                    console.error(err);
                    alert('Gagal memuat data presensi');
                });
        }
    </script>
@endpush
