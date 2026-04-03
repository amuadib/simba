@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Siswa</h4>
            <small class="text-muted">Daftar seluruh siswa terdaftar</small>
        </div>
        <x-breadcrumb />
    </div>

    {{-- Card --}}
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
            <h6 class="fw-bold text-primary"><i class="bi bi-pencil-square me-1"></i> Edit Data Siswa</h6>
                <form method="post" action="{{ route('siswa.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama Lengkap" required
                            value="{{ $data->nama }}"></div>
                    <div class="col"><input name="panggilan" class="form-control" placeholder="Panggilan"
                            value="{{ $data->panggilan ?? $data->nama }}">
                    </div>
                    <div class="col">
                        @foreach (['L','P'] as $k)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_kelamin" id="jenis_kelamin_{{ $k }}" value="{{ $k }}" {{ $data->jenis_kelamin == $k ? 'checked' : '' }}>
                            <label class="form-check-label" for="jenis_kelamin_{{ $k }}">{{ $k }}</label>
                        </div>
                        @endforeach
                    </div>
                    <div class="col"><input name="nisn" class="form-control" placeholder="NISN"
                            value="{{ $data->nisn }}">
                    </div>
                    <div class="col">
                        <select name="rombel_id" class="form-select">
                            <option value="">--Pilih Rombel--</option>
                            @foreach ($rombel as $k)
                                <option value="{{ $k->id }}" {{ $data->rombel_id == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col">
                    <select name="status" class="form-select">
                        <option value="">--Pilih Status--</option>
                        @foreach (config('local.status_siswa') as $k => $v)
                            <option value="{{ $k }}" {{ $data->status == $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                    </div>
                    @include('siswa._tag')

                    <div class="col-auto"><button class="btn btn-warning"><i class="bi bi-pencil"></i>Edit</button></div>
                </form>
            @else
            <h6 class="fw-bold text-primary"><i class="bi bi-plus-circle me-1"></i> Tambah Data Siswa</h6>
                <form method="post" action="{{ route('siswa.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama Lengkap" required></div>
                    <div class="col"><input name="panggilan" class="form-control" placeholder="Panggilan"></div>
                    <div class="col">
                        @foreach (['L','P'] as $k)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_kelamin" id="jenis_kelamin_{{ $k }}" value="{{ $k }}">
                            <label class="form-check-label" for="jenis_kelamin_{{ $k }}">{{ $k }}</label>
                        </div>
                        @endforeach
                    </div>
                    <div class="col"><input name="nisn" class="form-control" placeholder="NISN"></div>
                    <div class="col">
                        <select name="rombel_id" class="form-select">
                            <option value="">--Pilih Rombel--</option>
                            @foreach ($rombel as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col">
                    <select name="status" class="form-select">
                        <option value="">--Pilih Status--</option>
                        @foreach (config('local.status_siswa') as $k => $v)
                            <option value="{{ $k }}" {{ 1 == $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                    </div>
                    @include('siswa._tag')

                    <div class="col-auto">
                        <button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif

            <div class="mb-2">
                <h6 class="fw-bold text-primary"><i class="bi bi-upload me-1"></i> Impor Data Siswa</h6>
            <form method="post" action="{{ route('siswa.import') }}" class="row g-2 mb-3" enctype="multipart/form-data">
                @csrf
                <div class="col">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
                </div>
                <div class="col">
                    <select name="rombel_id" class="form-select">
                        <option value="">--Pilih Rombel--</option>
                        @foreach ($rombel as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-outline-warning"><i class="bi bi-upload"></i> Impor</button>
                </div>
            </form>
            </div>
            <hr>
            <div class="mb-2">
                <h6 class="fw-bold text-primary"><i class="bi bi-filter-circle me-1"></i> Filter Data Siswa</h6>    
            <form method="get" action="{{ route('siswa.index') }}" class="row g-3 mb-3" id='filterForm'>
                @csrf
                <div class="col">
                    <input type="text" name="q" class="form-control" placeholder="Cari nama siswa..."
                        value="{{ request('q') }}">
                </div>
                <div class="col">
                    <select name="rombel_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">--Pilih Rombel--</option>
                        @foreach ($rombel as $k)
                            <option value="{{ $k->id }}" {{ request('rombel_id') == $k->id ? 'selected' : '' }}>
                                {{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">--Pilih Status--</option>
                        @foreach (config('local.status_siswa') as $k => $v)
                            <option value="{{ $k }}" {{ request('status') == $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <select name="tag_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Tag --</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                                {{ $tag->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto">
                    <button class="btn btn-success">
                        <i class="bi bi-filter"></i>
                        Filter
                    </button>
                </div>
            </form>
            <div class="mb-3">
                
                <button type="button" class="btn btn-outline-info position-relative" data-bs-toggle="modal"
                    data-bs-target="#modalPreviewExport" onclick="loadPreviewExport()">
                    <i class="bi bi-eye"></i>
                    Preview Ekspor Siswa
                    <span class="@if(count(session('selected_siswa', [])) <1)d-none @endif position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="selected-siswa-count">
                        {{ count(session('selected_siswa', [])) }}
                    </span>
                </button>
                @if ($siswa->count() > 0 && (request('q') != '' || request('rombel_id') != '' || request('tag_id') != ''))
                    <a href="{{ route('siswa.export', 'filter') }}?{{ http_build_query(request()->except('_token')) }}" class="btn btn-success">
                        <i class="bi bi-download"></i>
                        Ekspor
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBulkAddTag">
                        <i class="bi bi-tags"></i>
                        Tambah Tag
                    </button>
                @endif
            </div>
            </div>
            <hr>

            <nav>
                {{ $siswa->links() }}
            </nav>
            <table class="table-bordered table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NISN</th>
                        <th>Rombel</th>
                        <th>Status</th>
                        <th>Tag</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!$siswa->count())
                        <tr>
                            <td colspan="5" class="text-center">Tidak ada data</td>
                        </tr>
                    @else
                        @foreach ($siswa as $s)
                            <tr>
                                <td>
                                    {!! setNama($s->nama, $s->panggilan, $s->jenis_kelamin) !!}
                                </td>
                                <td>{{ $s->nisn }}</td>
                                <td>{{ $s->rombel->nama }}</td>
                                <td>
                                    @if ($s->status == 1)
                                        <span class="badge bg-success">Aktif</span>
                                    @elseif ($s->status == 2)
                                        <span class="badge bg-info">Lulus</span>
                                    @elseif ($s->status == 3)
                                        <span class="badge bg-warning">Mutasi</span>
                                    @elseif ($s->status == 4)
                                        <span class="badge bg-secondary">Non-Aktif</span>
                                    @elseif ($s->status == 5)
                                        <span class="badge bg-dark">Almarhum</span>
                                    @elseif ($s->status == 6)
                                        <span class="badge bg-danger">Keluar</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($s->tags as $tag)
                                        <span class="badge bg-info me-1">
                                            {{ $tag->nama }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>

                                <td width="200">
                                    <button type="button" onclick="pilihSiswa(event, this, '{{ $s->id }}')"
                                        class="btn btn-sm btn-outline-info" title="Pilih untuk Ekspor">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>

                                    <a href="{{ route('siswa.edit', $s->id) }}" class="btn btn-sm btn-outline-warning"><i
                                            class="bi bi-pencil"></i></a>
                                    <form action="{{ route('siswa.destroy', $s->id) }}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Hapus data ini?')"><i
                                                class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            <nav>
                {{ $siswa->links() }}
            </nav>
        </div>
    </div>

    <div class="modal fade" id="modalPreviewExport" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Ekspor Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalPreviewExportBody">
                    <div class="py-4 text-center">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBulkAddTag" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tag ke Siswa Terpilih</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formBulkAddTag" action="{{ route('siswa.bulk-add-tag') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <select name="tag_id" id="tag_id" class="form-select">
                                <option value="">Pilih Tag</option>
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->nama }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="rombel_id" value="{{ request('rombel_id') }}">
                            <input type="hidden" name="status" value="{{ request('status') }}">
                            <input type="hidden" name="q" value="{{ request('q') }}">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        // ── DOM References ────────────────────────────────────────────
        const tagInput = document.getElementById('tagInput');
        const tagBox = document.getElementById('tagBox');
        const dropdown = document.getElementById('tagDropdown');

        // ── Tag Search State ──────────────────────────────────────────
        let debounceTimer = null;
        let selectedIndex = -1;
        let results = [];

        // ── Event Listeners ───────────────────────────────────────────
        tagBox.addEventListener('click', () => tagInput.focus());

        document.addEventListener('click', e => {
            if (!e.target.closest('#tagBox')) hideDropdown();
        });

        tagInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 1) return hideDropdown();

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => searchTag(q), 250);
        });

        tagInput.addEventListener('keydown', function(e) {
            if (e.key === ',') {
                e.preventDefault();
                const value = this.value.replace(',', '').trim();
                if (!value) return;
                addNewTag(value);
                this.value = '';
                hideDropdown();
                return;
            }

            if (dropdown.classList.contains('d-none')) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
                highlight();
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                highlight();
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0) selectTag(results[selectedIndex]);
            }
        });

        // ── Preview & Export ──────────────────────────────────────────
        function loadPreviewExport() {
            const body = document.getElementById('modalPreviewExportBody');
            fetch("{{ route('siswa.preview-export') }}")
                .then(res => res.text())
                .then(html => body.innerHTML = html)
                .catch(() => body.innerHTML = '<div class="alert alert-danger">Gagal memuat data.</div>');
        }

        function hapusSiswaPreviewExport(id, btn) {
            if (!confirm('Yakin hapus?')) return;

            fetch('{{ route('siswa.hapus-preview-export', ':id') }}'.replace(':id', id), {
                    method: 'get',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success == true) {
                        if (id != 'all') {
                            btn.closest('tr').remove();
                        } else {
                            bootstrap.Modal.getInstance(document.getElementById('modalPreviewExport')).hide();
                        }
                        if(data.count<1){
                            document.getElementById('selected-siswa-count').classList.add('d-none');
                        }else{
                            document.getElementById('selected-siswa-count').innerText = data.count;
                        }
                        showToast(data.message + '. Tersisa ' + data.count + ' siswa di daftar sementara');
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => console.error(err));
        }

        function pilihSiswa(event, btn, siswaId) {
            event.preventDefault();

            fetch("{{ route('siswa.pilih') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        siswa_id: siswaId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if(data.count>0){
                            document.getElementById('selected-siswa-count').classList.remove('d-none');
                            document.getElementById('selected-siswa-count').innerText = data.count;
                        }else{
                            document.getElementById('selected-siswa-count').classList.add('d-none');
                        }
                        showToast(data.message + '. Terdapat ' + data.count + ' siswa di daftar sementara');
                        loadPreviewExport();
                        btn.disabled = true;
                        btn.querySelector('i').className = 'bi bi-check-lg';
                        btn.classList.remove('btn-outline-primary');
                        btn.classList.add('disabled', 'cursor-not-allowed', 'btn-success');
                    }
                })
                .catch(err => console.error(err));
        }

        // ── Tag Search ────────────────────────────────────────────────
        function searchTag(q) {
            fetch(`/tags/search?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    results = data;
                    selectedIndex = -1;
                    renderDropdown();
                });
        }

        function renderDropdown() {
            if (!results.length) return hideDropdown();

            dropdown.innerHTML = results.map((t, i) => `
                <button type="button"
                        class="list-group-item list-group-item-action"
                        onclick="selectTag(${i})">
                    <span class="badge bg-primary me-2">${t.nama}</span>
                </button>
            `).join('');

            dropdown.classList.remove('d-none');
        }

        function highlight() {
            [...dropdown.children].forEach((el, i) => {
                el.classList.toggle('active', i === selectedIndex);
            });
        }

        function selectTag(index) {
            const tag = typeof index === 'number' ? results[index] : index;
            addExistingTag(tag);
            tagInput.value = '';
            hideDropdown();
        }

        function hideDropdown() {
            dropdown.classList.add('d-none');
        }

        // ── Tag Management ────────────────────────────────────────────
        function addExistingTag(tag) {
            if (document.querySelector(`.tag-chip[data-id="${tag.id}"]`)) return;

            const span = document.createElement('span');
            span.className = 'tag-chip bg-primary text-white';
            span.dataset.id = tag.id;
            span.dataset.name = tag.nama.toLowerCase();
            span.innerHTML = `
                ${tag.nama}
                <button type="button" onclick="removeTag(this)">×</button>
                <input type="hidden" name="tags[]" value="${tag.id}">
            `;
            tagBox.insertBefore(span, tagInput);
        }

        function addNewTag(name) {
            if (!name) return;

            const exists = [...document.querySelectorAll('.tag-chip')]
                .some(t => t.dataset.name === name.toLowerCase());
            if (exists) return;

            const span = document.createElement('span');
            span.className = 'tag-chip bg-primary text-white';
            span.dataset.name = name.toLowerCase();
            span.innerHTML = `
                ${name}
                <button type="button" onclick="removeTag(this)">×</button>
                <input type="hidden" name="tags_new[]" value="${name}">
            `;
            tagBox.insertBefore(span, tagInput);
        }

        function removeTag(btn) {
            btn.parentElement.remove();
        }
    </script>
@endpush
@push('styles')
    <style>
        .tag-chip {
            display: inline-flex;
            align-items: center;
            padding: 2px 4px;
            border-radius: 6px;
            font-size: 14px;
        }

        .tag-chip button {
            border: none;
            background: transparent;
            color: #fff;
            margin-left: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        #tagInput {
            min-width: 120px;
        }

        #tagDropdown .active {
            background-color: #0d6efd;
            color: white;
        }
    </style>
@endpush
