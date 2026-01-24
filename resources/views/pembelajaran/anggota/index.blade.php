@extends('layouts.app')

@section('title', 'Data Anggota Pembelajaran')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Anggota Pembelajaran</h4>
            <small class="text-muted">Daftar Anggota Pembelajaran {{ $pembelajaran->keterangan }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table-bordered table">
                <tr>
                    <th>Nama</th>
                    <th>Tahun Ajaran</th>
                    <th>Pelajaran</th>
                    <th>Aksi</th>
                </tr>
                @if (!$pembelajaran)
                    <tr>
                        <td colspan="4" class="text-center">Tidak ada data</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $pembelajaran->keterangan }}</td>
                        <td>{{ $pembelajaran->tahunAjaran->nama }}</td>
                        <td>{{ $pembelajaran->pelajaran->nama }}</td>
                        <td width="200">
                            <a href="{{ route('pembelajaran.index') }}" class="btn btn-sm btn-outline-info">Kembali</a>
                        </td>
                    </tr>
                @endif
            </table>

            <div class="rounded-3 p-3" style="border:2px dashed #0d6efd;">
                <h5>Data Anggota</h5>
                <form method="get" action="{{ route('pembelajaran.anggota.index', $pembelajaran->id) }}"
                    class="row g-2 mb-3" id='filterForm'>
                    @csrf
                    <div class="col">
                        <select name="rombel_id" class="form-select"
                            onchange="document.getElementById('filterForm').submit()">
                            <option value="">--Pilih Kelas--</option>
                            @foreach ($rombel as $k)
                                <option value="{{ $k->id }}" {{ request('rombel_id') == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button class="btn btn-success">Filter</button></div>
                </form>

                <div class="row">
                    <div class="col-md-5">
                        <label>
                            <h6>Tersedia</h6>
                        </label>
                        <select id="available" class="form-select" multiple size="10">
                            @foreach ($anggota_kelas as $a)
                                @unless (in_array($a->id, $anggota_selected))
                                    <option value="{{ $a->id }}">{{ $a->nama }}</option>
                                @endunless
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex flex-column justify-content-center gap-2">
                        {{-- <button type="button" class="btn btn-outline-success" onclick="moveAll('available','selected')">
                    ⏩
                </button> --}}
                        <button type="button" class="btn btn-outline-success"
                            onclick="moveSelected('available','selected')">
                            ▶️
                        </button>
                        <button type="button" class="btn btn-outline-danger"
                            onclick="moveSelected('selected','available')">
                            ◀️
                        </button>
                        {{-- <button type="button" class="btn btn-outline-danger" onclick="moveAll('selected','available')">
                    ⏪
                </button> --}}
                    </div>

                    <div class="col-md-5">
                        <label>
                            <h6>Terdaftar</h6>
                        </label>
                        <select id="selected" name="anggota[]" class="form-select" multiple size="10">
                            @foreach ($siswa as $s)
                                @if (in_array($s->id, $anggota_selected))
                                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const pembelajaranId = "{{ $pembelajaran->id }}";

        function moveSelected(from, to) {
            const fromEl = document.getElementById(from);
            const toEl = document.getElementById(to);
            const selectedOptions = Array.from(fromEl.selectedOptions);
            if (selectedOptions.length === 0) {
                return;
            }
            const ids = selectedOptions.map(o => o.value);
            fetch(`/pembelajaran/${pembelajaranId}/anggota/${to === 'selected' ? 'add' : 'remove'}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({
                    anggota: ids
                })
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Network error');
                }
                return response.json();
            }).then(data => {
                selectedOptions.forEach(option => {
                    fromEl.removeChild(option);
                    toEl.appendChild(option);
                });
            }).catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
        }
    </script>
@endpush
