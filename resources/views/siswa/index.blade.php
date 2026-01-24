@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Siswa</h4>
            <small class="text-muted">Daftar seluruh siswa terdaftar</small>
        </div>
        {{-- <a href="#" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Tambah Siswa
        </a> --}}
    </div>

    {{-- Card --}}
    <div class="card shadow-sm">
        <div class="card-body">
            @if ($action == 'edit')
                <form method="post" action="{{ route('siswa.update', $data) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required
                            value="{{ $data->nama }}"></div>
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

                    @include('siswa._tag')

                    <div class="col-auto"><button class="btn btn-warning">Edit</button></div>
                </form>
            @else
                <form method="post" action="{{ route('siswa.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col"><input name="nama" class="form-control" placeholder="Nama" required></div>
                    <div class="col"><input name="nisn" class="form-control" placeholder="NISN"></div>
                    <div class="col">
                        <select name="rombel_id" class="form-select">
                            <option value="">--Pilih Rombel--</option>
                            @foreach ($rombel as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    @include('siswa._tag')

                    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif
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
                <div class="col-auto"><button class="btn btn-outline-warning">Impor</button></div>
            </form>

            <form method="get" action="{{ route('siswa.index') }}" class="row g-2 mb-3" id='filterForm'>
                @csrf
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
                    <select name="tag_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Tag --</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                                {{ $tag->nama }}
                            </option>
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
                    <th>Tag</th>
                    <th>Aksi</th>
                </tr>
                @foreach ($siswa as $s)
                    <tr>
                        <td>{{ $s->nama }}</td>
                        <td>{{ $s->nisn }}</td>
                        <td>{{ $s->rombel->nama }}</td>
                        <td>
                            @forelse ($s->tags as $tag)
                                <span class="badge bg-info text-dark me-1">
                                    {{ $tag->nama }}
                                </span>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </td>

                        <td width="200">
                            <a href="{{ route('siswa.edit', $s->id) }}" class="btn btn-sm btn-outline-warning"><i
                                    class="bi bi-pencil"></i></a>
                            <form action="{{ route('siswa.destroy', $s->id) }}" method="post" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>

            <nav>
                {{ $siswa->links() }}
            </nav>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const tagInput = document.getElementById('tagInput');
        const tagBox = document.getElementById('tagBox');
        const dropdown = document.getElementById('tagDropdown');

        let debounceTimer = null;
        let selectedIndex = -1;
        let results = [];

        tagBox.addEventListener('click', () => tagInput.focus());

        tagInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 1) return hideDropdown();

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => searchTag(q), 250);
        });

        // tagInput.addEventListener('blur', () => {
        //     const value = tagInput.value.trim();
        //     if (value) {
        //         addNewTag(value);
        //         tagInput.value = '';
        //     }
        // });
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

                if (selectedIndex >= 0) {
                    selectTag(results[selectedIndex]);
                }
            }

        });

        document.addEventListener('click', e => {
            if (!e.target.closest('#tagBox')) hideDropdown();
        });

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

        function addExistingTag(tag) {
            if (document.querySelector(`.tag-chip[data-id="${tag.id}"]`)) return;

            const span = document.createElement('span');
            span.className = `tag-chip bg-primary text-white`;
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

        function hideDropdown() {
            dropdown.classList.add('d-none');
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
